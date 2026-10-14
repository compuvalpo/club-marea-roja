<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;

/**
 * Emisión y verificación de access tokens JWT (HS256) de corta duración.
 *
 * Buenas prácticas aplicadas (RFC 8725 "JWT BCP" y RFC 9068 "JWT access tokens"):
 * - algoritmo fijo en la verificación (no se acepta "none" ni otro "alg" del encabezado);
 * - encabezado "typ" = "at+jwt" para no confundirlo con otros JWT;
 * - se validan "iss", "aud", "exp", "nbf" y la presencia de "sub" y "sid" (sesión revocable en servidor);
 * - secreto de al menos 256 bits.
 */
class JwtService
{
    private const ALGORITHM = 'HS256';

    public const TYPE = 'at+jwt';

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $ttl,
        private readonly string $audience = 'marea-roja-web',
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET debe tener al menos 32 caracteres (php artisan jwt:secret).');
        }
    }

    public function issue(User $user, string $sessionId): string
    {
        $now = now()->getTimestamp();

        return JWT::encode([
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'sub' => (string) $user->id,
            'sid' => $sessionId,
            'role' => $user->role->value,
            'name' => $user->name,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ], $this->secret, self::ALGORITHM, null, ['typ' => self::TYPE]);
    }

    /**
     * Verifica firma y claims. Devuelve el id de usuario y el id de sesión.
     *
     * @return array{user_id: int, session_id: string}
     *
     * @throws AuthenticationException
     */
    public function verify(string $token): array
    {
        $headers = new \stdClass; // php-jwt sólo rellena los encabezados si se pasa un objeto
        try {
            JWT::$timestamp = now()->getTimestamp();
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM), $headers);
        } catch (ExpiredException) {
            throw new AuthenticationException('El token ha expirado.');
        } catch (\Throwable) {
            throw new AuthenticationException('Token inválido.');
        } finally {
            JWT::$timestamp = null;
        }

        $valid = ($headers->typ ?? null) === self::TYPE
            && ($claims->iss ?? null) === $this->issuer
            && ($claims->aud ?? null) === $this->audience
            && is_string($claims->sub ?? null) && ctype_digit($claims->sub)
            && is_string($claims->sid ?? null) && $claims->sid !== '';

        if (! $valid) {
            throw new AuthenticationException('Token inválido.');
        }

        return ['user_id' => (int) $claims->sub, 'session_id' => (string) $claims->sid];
    }

    public function ttl(): int
    {
        return $this->ttl;
    }
}
