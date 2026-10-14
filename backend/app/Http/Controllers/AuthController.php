<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permissions;
use App\Http\Resources\UserResource;
use App\Models\AuthSession;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly JwtService $jwt,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:180'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        return $this->session($request, $this->auth->login($data['email'], $data['password'], $request->ip(), $request->userAgent()));
    }

    public function refresh(Request $request): JsonResponse
    {
        return $this->session($request, $this->auth->refresh($request->cookie((string) config('jwt.cookie.name'))));
    }

    /**
     * Cierra la sesión. Con {"scope": "token"} sólo cierra la sesión del access token enviado y conserva la
     * cookie (la usa otra pestaña que inició una sesión distinta en el mismo navegador).
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenOnly = $request->input('scope') === 'token';
        $sessionId = null;
        if ($bearer = $request->bearerToken()) {
            try {
                $sessionId = $this->jwt->verify($bearer)['session_id'];
            } catch (\Throwable) {
                $sessionId = null; // token expirado o inválido: se cierra igualmente por la cookie
            }
        }

        $this->auth->logout($request->cookie((string) config('jwt.cookie.name')), $sessionId, $tokenOnly);

        $response = $this->noContent();

        return $tokenOnly ? $response : $response->withCookie($this->cookie('', -1));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var AuthSession $session */
        $session = $request->attributes->get('auth_session');

        return $this->data($this->userPayload($request, $this->user($request)) + [
            'session_id' => $session->id,
            'session_expires_at' => $session->expires_at->toIso8601String(),
        ]);
    }

    /**
     * @param  array{access_token: string, refresh_token: string, expires_in: int, user: User, session: AuthSession}  $result
     */
    private function session(Request $request, array $result): JsonResponse
    {
        $session = $result['session'];
        $remaining = max(1, (int) now()->diffInSeconds($session->expires_at));

        return $this->data([
            'access_token' => $result['access_token'],
            'token_type' => 'Bearer',
            'expires_in' => $result['expires_in'],
            'session_id' => $session->id,
            'session_expires_at' => $session->expires_at->toIso8601String(),
            'idle_timeout' => (int) config('jwt.idle_ttl'),
            'user' => $this->userPayload($request, $result['user']),
        ])->withCookie($this->cookie($result['refresh_token'], $remaining));
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(Request $request, User $user): array
    {
        return (new UserResource($user))->resolve($request) + ['permissions' => Permissions::for($user)];
    }

    private function cookie(string $value, int $seconds): Cookie
    {
        return Cookie::create(
            name: (string) config('jwt.cookie.name'),
            value: $value,
            expire: $seconds > 0 ? now()->addSeconds($seconds) : 1,
            path: (string) config('jwt.cookie.path'),
            secure: (bool) config('jwt.cookie.secure'),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }
}
