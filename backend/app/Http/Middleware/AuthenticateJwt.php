<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige un access token "Bearer" válido cuya sesión siga vigente en servidor, y autentica al usuario.
 *
 * Gracias a la comprobación de sesión, un logout, un cambio de rol/permisos o la desactivación del usuario
 * invalidan el token de inmediato (no hay que esperar a que expire).
 */
class AuthenticateJwt
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly AuthService $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            throw new AuthenticationException('No autenticado.');
        }

        $claims = $this->jwt->verify($token);
        $session = $this->auth->resolveSession($claims['user_id'], $claims['session_id']);

        /** @var User $user */
        $user = $session->user;
        Auth::setUser($user);
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('auth_session', $session);

        return $next($request);
    }
}
