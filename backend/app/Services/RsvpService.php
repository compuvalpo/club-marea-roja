<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Practice;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Enriquece prácticas con los pupilos/alumno del usuario y su confirmación (RSVP).
 */
class RsvpService
{
    public function __construct(private readonly AccessScope $scope) {}

    /**
     * @param  Collection<int, Practice>  $practices
     * @return array<int, list<array{athlete_id: int, full_name: string, rsvp: string}>> practice_id => entradas
     */
    public function myAthletesFor(User $user, Collection $practices): array
    {
        $ids = $this->scope->athleteIds($user) ?? [];
        $mine = Athlete::query()->whereIn('id', $ids)->get();
        $rsvp = Attendance::query()
            ->whereIn('athlete_id', $ids)
            ->whereIn('practice_id', $practices->pluck('id'))
            ->get()
            ->groupBy('practice_id');

        $result = [];
        foreach ($practices as $practice) {
            $result[$practice->id] = $mine
                ->filter(fn (Athlete $a): bool => $a->group_id === $practice->group_id)
                ->map(fn (Athlete $a): array => [
                    'athlete_id' => $a->id,
                    'full_name' => $a->full_name,
                    'rsvp' => (string) ($rsvp->get($practice->id)?->firstWhere('athlete_id', $a->id)->rsvp ?? 'pending'),
                ])->values()->all();
        }

        return $result;
    }
}
