<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Autenticación JWT y sesiones
|--------------------------------------------------------------------------
| - Access token: JWT HS256 de corta duración (RFC 8725 / RFC 9068: typ "at+jwt", iss, aud, sub, sid,
|   exp, nbf, iat, jti). Vive sólo en la memoria del navegador.
| - Refresh token: valor opaco de 256 bits en cookie HttpOnly; SameSite=Strict, rotado en cada uso
|   (RFC 9700 §4.14). La reutilización de uno ya rotado cierra la sesión.
| - Sesión en servidor (tabla auth_sessions): permite revocar al instante (logout, cambio de rol,
|   desactivación) y aplica tiempo máximo por inactividad y tiempo absoluto (OWASP Session Management).
*/

return [
    'secret' => env('JWT_SECRET'),
    'issuer' => env('JWT_ISSUER', 'marea-roja-api'),
    'audience' => env('JWT_AUDIENCE', 'marea-roja-web'),

    // Vida del access token (segundos). Recomendado 5–15 minutos.
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),

    // Cierre por inactividad: sin peticiones a la API durante este tiempo, la sesión deja de renovarse.
    'idle_ttl' => (int) env('JWT_IDLE_TTL', 1800),

    // Duración máxima absoluta de una sesión, aunque haya actividad (luego se exige iniciar sesión).
    'session_ttl' => (int) env('JWT_SESSION_TTL', 28800),

    // Una sola sesión activa por usuario: iniciar sesión en otra pestaña/dispositivo cierra la anterior.
    'single_session' => (bool) env('JWT_SINGLE_SESSION', true),

    // Margen (s) para dos renovaciones simultáneas del mismo navegador antes de considerarlo robo de token.
    'reuse_grace' => (int) env('JWT_REUSE_GRACE', 10),

    'cookie' => [
        'name' => 'refresh_token',
        'path' => '/api/v1/auth',
        'secure' => (bool) env('COOKIE_SECURE', true),
        'same_site' => 'strict',
    ],

    'login' => [
        // Por cuenta (correo): bloqueo tras N intentos fallidos.
        'max_attempts' => 5,
        'decay_seconds' => 900,
        // Por dirección IP (relleno de credenciales contra muchas cuentas).
        'max_attempts_per_ip' => (int) env('LOGIN_MAX_ATTEMPTS_PER_IP', 30),
    ],
];
