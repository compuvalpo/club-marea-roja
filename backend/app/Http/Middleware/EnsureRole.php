<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización por rol a nivel de ruta: ->middleware('role:admin,coach').
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowed = array_map(static fn (string $r): Role => Role::from($r), $roles);

        if (! $user instanceof User || ! $user->hasRole(...$allowed)) {
            throw new AuthorizationException('No tiene permisos para realizar esta acción.');
        }

        return $next($request);
    }
}
