<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\EventResource;
use App\Models\Athlete;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Services\AccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Eventos ocasionales (competencias, reuniones, actividades) en recintos propios o externos.
 */
class EventController extends Controller
{
    public function __construct(private readonly AccessScope $scope) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['from' => ['nullable', self::DATE], 'to' => ['nullable', self::DATE]]);
        $events = Event::query()->with('venue')
            ->when($q['from'] ?? null, fn ($b, $from) => $b->where('end_at', '>=', $from.' 00:00:00'))
            ->when($q['to'] ?? null, fn ($b, $to) => $b->where('start_at', '<=', $to.' 23:59:59'))
            ->orderBy('start_at')
            ->get();

        return EventResource::collection($events);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $user = $this->user($request);
        $only = $user->hasRole(Role::Admin, Role::Coach) ? null : ($this->scope->athleteIds($user) ?? []);

        $participants = $event->participants()->with('athlete')->get()
            ->filter(fn (EventParticipant $p): bool => $only === null || in_array($p->athlete_id, $only, true))
            ->sortBy(fn (EventParticipant $p): string => $p->athlete->last_name)
            ->map(fn (EventParticipant $p): array => [
                'athlete_id' => $p->athlete_id,
                'first_name' => $p->athlete->first_name,
                'last_name' => $p->athlete->last_name,
                'full_name' => $p->athlete->full_name,
                'rsvp' => $p->rsvp,
            ])->values()->all();

        $event->setAttribute('visible_participants', $participants);

        return $this->data(new EventResource($event->load('venue')));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $event = Event::query()->create($data + ['created_by' => $this->user($request)->id]);

        return $this->data(new EventResource($event->load('venue')), 201);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $event->update($this->validated($request, $event));

        return $this->data(new EventResource($event->load('venue')));
    }

    public function destroy(Event $event): JsonResponse
    {
        $event->delete();

        return $this->noContent();
    }

    /**
     * Convoca alumnos. Body: {athlete_ids: [1, 2, 3]}
     */
    public function addParticipants(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'athlete_ids' => ['required', 'array', 'min:1'],
            'athlete_ids.*' => ['integer', 'exists:athletes,id'],
        ]);
        foreach ($data['athlete_ids'] as $athleteId) {
            EventParticipant::query()->firstOrCreate(['event_id' => $event->id, 'athlete_id' => $athleteId], ['rsvp' => 'pending']);
        }

        return $this->data(['event_id' => $event->id, 'participants' => $event->participants()->count()], 201);
    }

    public function rsvp(Request $request, Event $event): JsonResponse
    {
        $data = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'status' => ['required', Rule::in(['confirmed', 'cancelled'])],
        ]);
        Gate::authorize('rsvp', Athlete::query()->findOrFail($data['athlete_id']));

        $participant = EventParticipant::query()->where(['event_id' => $event->id, 'athlete_id' => $data['athlete_id']])->first();
        if ($participant === null) {
            throw ValidationException::withMessages(['athlete_id' => ['El alumno no está convocado a este evento.']]);
        }
        $participant->update(['rsvp' => $data['status']]);

        return $this->data(['event_id' => $event->id, 'athlete_id' => $participant->athlete_id, 'rsvp' => $data['status']]);
    }

    /**
     * El lugar puede ser un recinto registrado o una ubicación libre (uno de los dos es obligatorio).
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Event $current = null): array
    {
        if ($current !== null) {
            $request->mergeIfMissing([
                'start_at' => substr($current->start_at, 0, 16),
                'end_at' => substr($current->end_at, 0, 16),
            ]);
        }
        $req = $current ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$req, 'string', 'min:3', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => [$req, Rule::in(Event::TYPES)],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'start_at' => ['required', self::DATETIME],
            'end_at' => ['required', self::DATETIME, 'after:start_at'],
        ]);

        $venue = array_key_exists('venue_id', $data) ? $data['venue_id'] : $current?->venue_id;
        $text = array_key_exists('location_text', $data) ? $data['location_text'] : $current?->location_text;
        if ($venue === null && ($text === null || $text === '')) {
            throw ValidationException::withMessages(['venue_id' => ['Debe elegir un recinto o indicar la ubicación.']]);
        }

        $data['start_at'] .= ':00';
        $data['end_at'] .= ':00';

        return $data;
    }
}
