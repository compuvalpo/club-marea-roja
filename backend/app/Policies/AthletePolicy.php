<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Athlete;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Auth\Access\Response;

/**
 * Autorización por fila de alumnos (fichas, estadísticas y confirmaciones).
 */
class AthletePolicy
{
    public function __construct(private readonly AccessScope $scope) {}

    public function view(User $user, Athlete $athlete): Response
    {
        return $this->scope->canViewAthlete($user, $athlete->id)
            ? Response::allow()
            : Response::deny('No tiene permisos para ver a este alumno.');
    }

    public function rsvp(User $user, Athlete $athlete): Response
    {
        return $this->scope->canRsvpFor($user, $athlete->id)
            ? Response::allow()
            : Response::deny('Sólo puede confirmar o cancelar asistencia propia o de sus pupilos.');
    }
}
