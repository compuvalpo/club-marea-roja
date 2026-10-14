<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Role;
use App\Models\Athlete;
use App\Models\Practice;
use App\Models\TrainingGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visibilidad por fila según el rol.
 *
 * - Admin: todo (null = sin restricción).
 * - Entrenador: grupos que dirige o donde tiene prácticas asignadas, y sus alumnos.
 * - Apoderado: sus pupilos.
 * - Alumno: su propia ficha.
 */
class AccessScope
{
    /**
     * @return list<int>|null
     */
    public function athleteIds(User $user): ?array
    {
        return match ($user->role) {
            Role::Admin => null,
            Role::Coach => $this->ids(Athlete::query()->whereIn('group_id', $this->coachGroupIds($user))),
            Role::Guardian => $this->ids(Athlete::query()->where('guardian_id', $user->id)),
            Role::Athlete => $this->ids(Athlete::query()->where('user_id', $user->id)),
        };
    }

    /**
     * @return list<int>|null
     */
    public function groupIds(User $user): ?array
    {
        return match ($user->role) {
            Role::Admin => null,
            Role::Coach => $this->coachGroupIds($user),
            default => array_values(array_unique(array_map(
                'intval',
                Athlete::query()->whereIn('id', $this->athleteIds($user) ?? [])->whereNotNull('group_id')->pluck('group_id')->all(),
            ))),
        };
    }

    public function canViewAthlete(User $user, int $athleteId): bool
    {
        $ids = $this->athleteIds($user);

        return $ids === null || in_array($athleteId, $ids, true);
    }

    /**
     * Apoderados (sus pupilos) y alumnos (propia) confirman asistencia; el admin también puede.
     */
    public function canRsvpFor(User $user, int $athleteId): bool
    {
        return match ($user->role) {
            Role::Admin => true,
            Role::Coach => false,
            default => in_array($athleteId, $this->athleteIds($user) ?? [], true),
        };
    }

    /**
     * @return list<int>
     */
    public function coachGroupIds(User $user): array
    {
        $own = TrainingGroup::query()->where('coach_id', $user->id)->pluck('id')->all();
        $assigned = Practice::query()->where('coach_id', $user->id)->distinct()->pluck('group_id')->all();

        return array_values(array_unique(array_map('intval', [...$own, ...$assigned])));
    }

    /**
     * @param  Builder<Athlete>  $query
     * @return list<int>
     */
    private function ids($query): array
    {
        return array_values(array_map('intval', $query->pluck('id')->all()));
    }
}
