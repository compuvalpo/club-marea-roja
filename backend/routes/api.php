<?php

declare(strict_types=1);

use App\Http\Controllers\AthleteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Convención: la autorización por PERMISO se declara aquí ('can:permiso', matriz en App\Auth\Permissions,
| la misma que recibe el frontend); la autorización por FILA (qué alumno/práctica) se resuelve con Policies
| dentro del controlador.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function (): void {
    // Endpoints que usan la cookie de sesión: verificación de Origin (CSRF) y límite por IP.
    Route::middleware(['origin', 'throttle:auth'])->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['jwt', 'throttle:api'])->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('can:dashboard.view');

        // Usuarios
        Route::middleware('can:users.manage')->group(function (): void {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::patch('/users/{user}', [UserController::class, 'update']);
            Route::delete('/users/{user}', [UserController::class, 'destroy']);
            Route::post('/users/{user}/revoke-sessions', [UserController::class, 'revokeSessions']);
        });

        // Alumnos
        Route::middleware('can:athletes.view')->group(function (): void {
            Route::get('/athletes', [AthleteController::class, 'index']);
            Route::get('/athletes/{athlete}', [AthleteController::class, 'show']);
            Route::get('/athletes/{athlete}/stats', [AthleteController::class, 'stats']);
        });
        Route::middleware('can:athletes.manage')->group(function (): void {
            Route::post('/athletes', [AthleteController::class, 'store']);
            Route::patch('/athletes/{athlete}', [AthleteController::class, 'update']);
            Route::delete('/athletes/{athlete}', [AthleteController::class, 'destroy']);
        });

        // Catálogos (lectura para todos los roles autenticados)
        Route::get('/venues', [CatalogController::class, 'venues']);
        Route::get('/groups', [CatalogController::class, 'groups']);
        Route::middleware('can:catalog.manage')->group(function (): void {
            Route::post('/venues', [CatalogController::class, 'storeVenue']);
            Route::patch('/venues/{venue}', [CatalogController::class, 'updateVenue']);
            Route::delete('/venues/{venue}', [CatalogController::class, 'destroyVenue']);
            Route::post('/groups', [CatalogController::class, 'storeGroup']);
            Route::patch('/groups/{group}', [CatalogController::class, 'updateGroup']);
            Route::delete('/groups/{group}', [CatalogController::class, 'destroyGroup']);
        });

        // Horarios recurrentes
        Route::middleware('can:schedules.manage')->group(function (): void {
            Route::get('/schedules', [ScheduleController::class, 'index']);
            Route::post('/schedules', [ScheduleController::class, 'store']);
            Route::patch('/schedules/{schedule}', [ScheduleController::class, 'update']);
            Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy']);
            Route::post('/schedules/{schedule}/generate', [ScheduleController::class, 'generate']);
        });

        // Prácticas
        Route::middleware('can:practices.view')->group(function (): void {
            Route::get('/practices', [PracticeController::class, 'index']);
            Route::get('/practices/{practice}', [PracticeController::class, 'show']);
        });
        Route::post('/practices', [PracticeController::class, 'store'])->middleware('can:practices.plan');
        Route::delete('/practices/{practice}', [PracticeController::class, 'destroy'])->middleware('can:practices.plan');
        Route::middleware('can:practices.manage')->group(function (): void {
            Route::patch('/practices/{practice}', [PracticeController::class, 'update']);
            Route::put('/practices/{practice}/attendance', [PracticeController::class, 'saveAttendance']);
            Route::post('/practices/{practice}/measurements', [PracticeController::class, 'addMeasurement']);
            Route::post('/practices/{practice}/incidents', [PracticeController::class, 'addIncident']);
        });
        Route::post('/practices/{practice}/rsvp', [PracticeController::class, 'rsvp'])->middleware('can:practices.rsvp');

        // Eventos
        Route::middleware('can:events.view')->group(function (): void {
            Route::get('/events', [EventController::class, 'index']);
            Route::get('/events/{event}', [EventController::class, 'show']);
        });
        Route::middleware('can:events.manage')->group(function (): void {
            Route::post('/events', [EventController::class, 'store']);
            Route::patch('/events/{event}', [EventController::class, 'update']);
            Route::delete('/events/{event}', [EventController::class, 'destroy']);
            Route::post('/events/{event}/participants', [EventController::class, 'addParticipants']);
        });
        Route::post('/events/{event}/rsvp', [EventController::class, 'rsvp'])->middleware('can:events.rsvp');

        // Finanzas
        Route::get('/me/charges', [FinanceController::class, 'myCharges'])->middleware('can:finance.own');
        Route::prefix('finance')->middleware('can:finance.manage')->group(function (): void {
            Route::get('/charges', [FinanceController::class, 'charges']);
            Route::post('/charges/generate', [FinanceController::class, 'generateCharges']);
            Route::post('/payments', [FinanceController::class, 'registerPayment']);
            Route::get('/delinquents', [FinanceController::class, 'delinquents']);
            Route::get('/summary', [FinanceController::class, 'summary']);
            Route::get('/transactions', [FinanceController::class, 'transactions']);
            Route::post('/transactions', [FinanceController::class, 'storeTransaction']);
            Route::delete('/transactions/{transaction}', [FinanceController::class, 'destroyTransaction']);
            Route::get('/fixed-expenses', [FinanceController::class, 'fixedExpenses']);
            Route::post('/fixed-expenses', [FinanceController::class, 'storeFixedExpense']);
            Route::post('/fixed-expenses/apply', [FinanceController::class, 'applyFixedExpenses']);
            Route::patch('/fixed-expenses/{fixedExpense}', [FinanceController::class, 'updateFixedExpense']);
            Route::delete('/fixed-expenses/{fixedExpense}', [FinanceController::class, 'destroyFixedExpense']);
        });
    });
});
