<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AuthSession;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Casos de uso de autenticación y sesiones: login, rotación del refresh token, logout y revocación.
 *
 * @phpstan-type SessionResult array{access_token: string, refresh_token: string, expires_in: int, user: User, session: AuthSession}
 */
class AuthService
{
    /** Hash de relleno para que un correo inexistente tarde lo mismo que una contraseña incorrecta. */
    private static ?string $dummyHash = null;

    public function __construct(private readonly JwtService $jwt) {}

    /**
     * @return SessionResult
     */
    public function login(string $email, string $password, ?string $ip = null, ?string $userAgent = null): array
    {
        $email = mb_strtolower(trim($email));
        $accountKey = 'login:'.$email;
        $ipKey = 'login-ip:'.($ip ?? 'desconocida');
        $decay = (int) config('jwt.login.decay_seconds');

        foreach ([[$accountKey, (int) config('jwt.login.max_attempts')], [$ipKey, (int) config('jwt.login.max_attempts_per_ip')]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
                throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), "Demasiados intentos fallidos. Intente nuevamente en {$minutes} minutos.");
            }
        }

        $user = User::query()->where('email', $email)->first();
        $hash = $user !== null ? $user->password : (self::$dummyHash ??= Hash::make(Str::random(40)));
        $passwordOk = Hash::check($password, $hash);

        if ($user === null || ! $passwordOk || ! $user->active) {
            RateLimiter::hit($accountKey, $decay);
            RateLimiter::hit($ipKey, $decay);
            Log::notice('auth.login_failed', ['user_id' => $user?->id, 'ip' => $ip]);
            throw new AuthenticationException('Credenciales inválidas.');
        }

        RateLimiter::clear($accountKey);

        return DB::transaction(function () use ($user, $ip, $userAgent): array {
            if (config('jwt.single_session')) {
                $this->revokeAllFor($user, AuthSession::REASON_REPLACED);
            }

            $session = AuthSession::query()->create([
                'id' => bin2hex(random_bytes(32)),
                'user_id' => $user->id,
                'ip_address' => $ip,
                'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
                'last_activity_at' => now(),
                'expires_at' => now()->addSeconds((int) config('jwt.session_ttl')),
            ]);
            Log::info('auth.login', ['user_id' => $user->id, 'ip' => $ip]);

            return $this->issueTokens($user, $session);
        });
    }

    /**
     * Rota el refresh token. Si se reutiliza uno ya rotado (posible robo), cierra la sesión completa.
     *
     * @return SessionResult
     */
    public function refresh(?string $plainToken): array
    {
        $stored = $plainToken ? RefreshToken::query()->with(['session', 'user'])->where('token_hash', hash('sha256', $plainToken))->first() : null;
        $session = $stored?->session;

        if ($stored === null || $session === null) {
            throw new AuthenticationException('Sesión inválida. Inicie sesión nuevamente.');
        }

        if ($stored->revoked) {
            // Dos pestañas del mismo navegador pueden renovar casi a la vez: dentro del margen no es un robo.
            $grace = (int) config('jwt.reuse_grace');
            if ($session->revoked_at === null && $stored->revoked_at !== null && $stored->revoked_at->diffInSeconds(now()) <= $grace) {
                throw new AuthenticationException('La sesión se renovó en otra pestaña. Reintente.');
            }
            if ($session->revoked_at !== null) {
                // La sesión ya estaba cerrada (logout, otra sesión, permisos...): se informa el motivo real.
                throw new AuthenticationException($session->closedMessage());
            }
            $this->revokeSession($session, AuthSession::REASON_REUSE);
            Log::warning('auth.refresh_token_reuse', ['user_id' => $stored->user_id, 'session' => substr($session->id, 0, 8)]);

            throw new AuthenticationException('Sesión inválida. Inicie sesión nuevamente.');
        }

        $this->assertSessionUsable($session, $stored->user);

        return DB::transaction(function () use ($stored, $session): array {
            $stored->update(['revoked' => true, 'revoked_at' => now()]);
            $session->update(['last_activity_at' => now()]);

            /** @var User $user */
            $user = $stored->user;

            return $this->issueTokens($user, $session);
        });
    }

    /**
     * Cierra la sesión del navegador (cookie) y/o la del access token recibido.
     * Con $tokenOnly sólo se cierra la sesión del access token (no se toca la cookie compartida entre pestañas).
     */
    public function logout(?string $plainToken, ?string $bearerSessionId = null, bool $tokenOnly = false): void
    {
        $ids = [];
        if ($bearerSessionId !== null) {
            $ids[] = $bearerSessionId;
        }
        if (! $tokenOnly && $plainToken) {
            $ids[] = RefreshToken::query()->where('token_hash', hash('sha256', $plainToken))->value('session_id');
        }

        AuthSession::query()->whereIn('id', array_filter($ids))->whereNull('revoked_at')->get()
            ->each(fn (AuthSession $s) => $this->revokeSession($s, AuthSession::REASON_LOGOUT));
    }

    /**
     * Valida la sesión de un access token en cada petición y registra actividad.
     *
     * @throws AuthenticationException
     */
    public function resolveSession(int $userId, string $sessionId): AuthSession
    {
        $session = AuthSession::query()->with('user')->find($sessionId);
        if ($session === null || $session->user_id !== $userId) {
            throw new AuthenticationException('Sesión inválida. Inicie sesión nuevamente.');
        }

        $this->assertSessionUsable($session, $session->user);

        // Se registra la actividad como máximo una vez por minuto para no escribir en cada petición.
        if ($session->last_activity_at->diffInSeconds(now()) >= 60) {
            $session->update(['last_activity_at' => now()]);
        }

        return $session;
    }

    /**
     * Revoca todas las sesiones activas de un usuario (cambio de rol, desactivación, nueva sesión, etc.).
     */
    public function revokeAllFor(User $user, string $reason, ?string $exceptSessionId = null): int
    {
        $sessions = AuthSession::query()->where('user_id', $user->id)->whereNull('revoked_at')
            ->when($exceptSessionId !== null, fn ($q) => $q->where('id', '!=', $exceptSessionId))
            ->get();
        $sessions->each(fn (AuthSession $s) => $this->revokeSession($s, $reason));

        if ($sessions->isNotEmpty()) {
            Log::info('auth.sessions_revoked', ['user_id' => $user->id, 'reason' => $reason, 'count' => $sessions->count()]);
        }

        return $sessions->count();
    }

    private function revokeSession(AuthSession $session, string $reason): void
    {
        $session->update(['revoked_at' => now(), 'revoked_reason' => $reason]);
        RefreshToken::query()->where('session_id', $session->id)->where('revoked', false)->update(['revoked' => true, 'revoked_at' => now()]);
    }

    /**
     * @throws AuthenticationException
     */
    private function assertSessionUsable(AuthSession $session, ?User $user): void
    {
        if ($session->revoked_at !== null || $session->expires_at->isPast()) {
            throw new AuthenticationException($session->closedMessage());
        }
        if ($session->isIdle()) {
            $this->revokeSession($session, AuthSession::REASON_IDLE);
            throw new AuthenticationException($session->closedMessage());
        }
        if ($user === null || ! $user->active) {
            $this->revokeSession($session, AuthSession::REASON_PERMISSIONS);
            throw new AuthenticationException('Su cuenta está desactivada.');
        }
    }

    /**
     * @return SessionResult
     */
    private function issueTokens(User $user, AuthSession $session): array
    {
        $refresh = bin2hex(random_bytes(32));
        RefreshToken::query()->create([
            'session_id' => $session->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $refresh),
            'expires_at' => $session->expires_at,
            'revoked' => false,
        ]);

        return [
            'access_token' => $this->jwt->issue($user, $session->id),
            'refresh_token' => $refresh,
            'expires_in' => $this->jwt->ttl(),
            'user' => $user,
            'session' => $session,
        ];
    }
}
