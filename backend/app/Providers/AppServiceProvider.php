<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Permissions;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class, fn (): JwtService => new JwtService(
            (string) config('jwt.secret'),
            (string) config('jwt.issuer'),
            (int) config('jwt.access_ttl'),
            (string) config('jwt.audience'),
        ));
    }

    public function boot(): void
    {
        // Las respuestas de colección se envuelven en {"data": [...]} (contrato de la API).
        JsonResource::wrap('data');

        // Un Gate por permiso de la matriz RBAC: las rutas usan ->middleware('can:permiso').
        foreach (Permissions::all() as $permission) {
            Gate::define($permission, static fn (User $user): Response => Permissions::allows($user, $permission)
                ? Response::allow()
                : Response::deny('No tiene permisos para realizar esta acción.'));
        }

        // Límite general para los endpoints de autenticación (por IP).
        RateLimiter::for('auth', static fn (Request $request): Limit => Limit::perMinute(60)->by('auth:'.$request->ip()));
        // Límite general de la API autenticada (por usuario o IP).
        RateLimiter::for('api', static fn (Request $request): Limit => Limit::perMinute(300)->by('api:'.($request->bearerToken() !== null ? sha1($request->bearerToken()) : $request->ip())));
    }
}
