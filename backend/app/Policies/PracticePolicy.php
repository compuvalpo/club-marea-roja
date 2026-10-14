<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Role;
use App\Models\Practice;
use App\Models\User;
use App\Services\AccessScope;
use Illuminate\Auth\Access\Response;

/**
 * Autorización por fila de prácticas.
 */
class PracticePolicy
{
    public function __construct(private readonly AccessScope $scope) {}

    public function view(User $user, Practice $practice): bool
    {
        if ($user->isAdmin() || $practice->isAssignedTo($user)) {
            return true;
        }

        return in_array($practice->group_id, $this->scope->groupIds($user) ?? [], true);
    }

    /** Pasar lista, objetivos, marcas e incidencias: admin o entrenador asignado. */
    public function manage(User $user, Practice $practice): Response
    {
        return $user->isAdmin() || ($user->role === Role::Coach && $practice->isAssignedTo($user))
            ? Response::allow()
            : Response::deny('Sólo el entrenador asignado puede gestionar esta práctica.');
    }
}
