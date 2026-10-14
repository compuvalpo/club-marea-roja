<?php

declare(strict_types=1);

/*
| CORS con lista blanca explícita (nunca "*", porque se usan cookies).
| En desarrollo el proxy de Vite hace que la SPA y la API compartan origen.
*/

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    // Se descartan comodines: con credenciales (cookies) un "*" sería inseguro y los navegadores lo rechazan.
    'allowed_origins' => array_values(array_filter(
        array_map(static fn (string $o): string => rtrim(trim($o), '/'), explode(',', (string) env('CORS_ORIGINS', 'http://localhost:5173'))),
        static fn (string $o): bool => $o !== '' && ! str_contains($o, '*') && preg_match('#^https?://[^/\s]+$#', $o) === 1,
    )),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => true,
];
