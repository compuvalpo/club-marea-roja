<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controladores delgados: validan, autorizan, delegan en servicios y responden.
 */
abstract class Controller
{
    /** Validación de formatos usados en toda la API. */
    protected const DATE = 'date_format:Y-m-d';

    protected const TIME = 'date_format:H:i';

    protected const DATETIME = 'date_format:Y-m-d H:i';

    protected const PERIOD = 'regex:/^\d{4}-(0[1-9]|1[0-2])$/';

    protected function data(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
