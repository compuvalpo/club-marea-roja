<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyOrigin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => AuthenticateJwt::class,
            'role' => EnsureRole::class,
            'origin' => VerifyOrigin::class,
        ]);
        $middleware->api(append: [SecurityHeaders::class]);
        // La cookie de refresco no se cifra: es un valor aleatorio opaco cuyo hash se guarda en BD.
        $middleware->encryptCookies(except: ['refresh_token']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todas las respuestas de error de la API: {"error": {"message": "...", "errors": {...}}}
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            [$status, $message, $errors] = match (true) {
                $e instanceof ValidationException => [422, 'Los datos enviados no son válidos.', $e->errors()],
                $e instanceof AuthenticationException => [401, $e->getMessage() ?: 'No autenticado.', null],
                $e instanceof AuthorizationException => [403, $e->getMessage() ?: 'No tiene permisos para realizar esta acción.', null],
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'Recurso no encontrado.', null],
                $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: 'Error de solicitud.', null],
                default => [500, 'Error interno del servidor.', null],
            };

            if ($status === 403 && $e->getPrevious() instanceof AuthorizationException) {
                $message = $e->getPrevious()->getMessage();
            }
            if ($status === 403 && $message === 'This action is unauthorized.') {
                $message = 'No tiene permisos para realizar esta acción.';
            }
            if ($status === 429 && $message === 'Too Many Attempts.') {
                $message = 'Demasiadas solicitudes. Espere un momento e intente nuevamente.';
            }

            $body = array_filter(['message' => $message, 'errors' => $errors]);
            if ($status >= 500) {
                Log::error($e);
                if (config('app.debug')) {
                    $body['debug'] = $e->getMessage();
                }
            }

            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return response()->json(['error' => $body], $status, $headers);
        });
    })->create();
