<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defensa CSRF adicional para los endpoints que usan la cookie de sesión (login, refresh, logout).
 *
 * Además de SameSite=Strict, rechaza peticiones cuyo encabezado Origin no esté en la lista blanca o que el
 * navegador marque como de otro sitio (Sec-Fetch-Site: cross-site). Sin encabezados (p. ej. curl) se permite.
 */
class VerifyOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        if ($origin !== null && $origin !== 'null' && ! in_array(rtrim($origin, '/'), $this->allowed($request), true)) {
            throw new AccessDeniedHttpException('Origen no permitido.');
        }
        if ($origin === 'null' || $request->headers->get('Sec-Fetch-Site') === 'cross-site') {
            throw new AccessDeniedHttpException('Origen no permitido.');
        }

        return $next($request);
    }

    /**
     * @return list<string>
     */
    private function allowed(Request $request): array
    {
        /** @var list<string> $origins */
        $origins = (array) config('cors.allowed_origins', []);

        return array_values(array_unique([
            ...array_map(static fn (string $o): string => rtrim($o, '/'), $origins),
            rtrim((string) config('app.url'), '/'),
            $request->getSchemeAndHttpHost(),
        ]));
    }
}
