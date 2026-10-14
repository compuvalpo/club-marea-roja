<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\IncidentResource;
use App\Http\Resources\MeasurementResource;
use App\Http\Resources\PracticeResource;
use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Incident;
use App\Models\Measurement;
use App\Models\Practice;
use App\Services\AccessScope;
use App\Services\RsvpService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Prácticas: calendario, gestión del entrenador (lista, objetivos, marcas, incidencias) y RSVP.
 */
class PracticeController extends Controller
{
    private const MAX_RANGE_DAYS = 120;

    /** Campos que el entrenador asignado puede modificar. */
    private const COACH_FIELDS = ['objectives', 'notes', 'start_time', 'end_time', 'venue_id', 'status'];

    public function __construct(
        private readonly AccessScope $scope,
        private readonly RsvpService $rsvp,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $q = $request->validate([
            'from' => ['required', self::DATE],
            'to' => ['required', self::DATE],
            'group_id' => ['nullable', 'integer'],
            'coach_id' => ['nullable', 'integer'],
        ]);
        if ($q['from'] > $q['to'] || CarbonImmutable::parse($q['from'])->diffInDays(CarbonImmutable::parse($q['to'])) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => ['Rango de fechas inválido (máximo 120 días).']]);
        }

        $query = Practice::query()->with(['group', 'coach', 'venue'])
            ->whereBetween('date', [$q['from'], $q['to']])
            ->orderBy('date')->orderBy('start_time');

        match ($user->role) {
            Role::Admin => $query
                ->when($q['coach_id'] ?? null, fn ($b, $c) => $b->where('coach_id', $c))
                ->when($q['group_id'] ?? null, fn ($b, $g) => $b->where('group_id', $g)),
            Role::Coach => $query->where('coach_id', $user->id),
            default => $query->whereIn('group_id', $this->scope->groupIds($user) ?? []),
        };

        $practices = $query->get();
        if ($user->hasRole(Role::Guardian, Role::Athlete)) {
            $mine = $this->rsvp->myAthletesFor($user, $practices);
            $practices->each(fn (Practice $p) => $p->setAttribute('my_athletes', $mine[$p->id] ?? []));
        }

        return $this->data(PracticeResource::collection($practices));
    }

    public function show(Request $request, Practice $practice): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('view', $practice);
        $canManage = Gate::allows('manage', $practice);

        $attendance = $this->attendanceList($practice);
        $payload = (new PracticeResource($practice->load(['group', 'coach', 'venue'])))->resolve($request);
        $payload['can_manage'] = $canManage;

        if ($canManage) {
            $payload['attendance'] = $attendance;
            $payload['measurements'] = MeasurementResource::collection(
                $practice->measurements()->with('athlete')->latest('recorded_at')->latest('id')->get(),
            )->resolve($request);
            $payload['incidents'] = IncidentResource::collection(
                $practice->incidents()->with(['athlete', 'recorder'])->latest()->latest('id')->get(),
            )->resolve($request);
        } else {
            $mine = $this->scope->athleteIds($user) ?? [];
            $payload['attendance'] = array_values(array_filter($attendance, fn (array $a): bool => in_array($a['athlete_id'], $mine, true)));
        }

        return $this->data($payload);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['status'] ??= 'scheduled';
        $this->ensureSlotIsFree((int) $data['group_id'], $data['date'], $data['start_time']);
        $practice = Practice::query()->create($data);

        return $this->data(new PracticeResource($practice->load(['group', 'coach', 'venue'])), 201);
    }

    public function update(Request $request, Practice $practice): JsonResponse
    {
        $user = $this->user($request);
        Gate::authorize('view', $practice);
        Gate::authorize('manage', $practice);

        $sent = array_keys($request->only(array_keys($this->rules())));
        if (! $user->isAdmin() && ($forbidden = array_values(array_diff($sent, self::COACH_FIELDS))) !== []) {
            throw new AuthorizationException('El entrenador no puede modificar: '.implode(', ', $forbidden).'.');
        }

        // Para validar término > inicio cuando sólo se envía uno de los dos.
        $request->mergeIfMissing([
            'start_time' => substr($practice->start_time, 0, 5),
            'end_time' => substr($practice->end_time, 0, 5),
        ]);
        $data = $request->validate($this->rules(true));

        $this->ensureSlotIsFree(
            (int) ($data['group_id'] ?? $practice->group_id),
            $data['date'] ?? $practice->date,
            $data['start_time'] ?? $practice->start_time,
            $practice->id,
        );

        $practice->update($data);

        return $this->data(new PracticeResource($practice->load(['group', 'coach', 'venue'])));
    }

    /**
     * Impide dos prácticas del mismo grupo con la misma fecha y hora de inicio.
     */
    private function ensureSlotIsFree(int $groupId, string $date, string $startTime, ?int $ignoreId = null): void
    {
        $time = substr($startTime, 0, 5);
        $taken = Practice::query()
            ->where('group_id', $groupId)
            ->where('date', $date)
            ->where('start_time', 'like', $time.'%')
            ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($taken) {
            throw new ConflictHttpException(sprintf(
                'El día %s ya cuenta con una programación para el horario %s.',
                Carbon::parse($date)->format('d-m-Y'),
                $time,
            ));
        }
    }

    public function destroy(Practice $practice): JsonResponse
    {
        $practice->delete();

        return $this->noContent();
    }

    /**
     * Pasar lista. Body: {items: [{athlete_id, present, comment?}]}
     */
    public function saveAttendance(Request $request, Practice $practice): JsonResponse
    {
        Gate::authorize('manage', $practice);
        $groupAthletes = array_column($this->attendanceList($practice), 'athlete_id');

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.athlete_id' => ['required', 'integer', Rule::in($groupAthletes)],
            'items.*.present' => ['required', 'boolean'],
            'items.*.comment' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $practice): void {
            foreach ($data['items'] as $item) {
                Attendance::query()->updateOrCreate(
                    ['practice_id' => $practice->id, 'athlete_id' => $item['athlete_id']],
                    ['present' => (bool) $item['present'], 'comment' => $item['comment'] ?? null],
                );
            }
            if ($practice->status === 'scheduled') {
                $practice->update(['status' => 'done']);
            }
        });

        return $this->data($this->attendanceList($practice));
    }

    /**
     * Confirmar o cancelar asistencia (apoderado / alumno). Body: {athlete_id, status}
     */
    public function rsvp(Request $request, Practice $practice): JsonResponse
    {
        Gate::authorize('view', $practice);
        $data = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'status' => ['required', Rule::in(['confirmed', 'cancelled'])],
            'comment' => ['nullable', 'string', 'max:255'],
        ]);
        $athlete = Athlete::query()->findOrFail($data['athlete_id']);
        Gate::authorize('rsvp', $athlete);

        if ($athlete->group_id !== $practice->group_id) {
            throw ValidationException::withMessages(['athlete_id' => ['El alumno no pertenece al grupo de esta práctica.']]);
        }
        if ($practice->status !== 'scheduled' || $practice->date < today()->toDateString()) {
            throw new ConflictHttpException('Sólo se puede confirmar o cancelar prácticas futuras programadas.');
        }

        Attendance::query()->updateOrCreate(
            ['practice_id' => $practice->id, 'athlete_id' => $athlete->id],
            ['rsvp' => $data['status'], 'comment' => $data['comment'] ?? null],
        );

        return $this->data(['practice_id' => $practice->id, 'athlete_id' => $athlete->id, 'rsvp' => $data['status']]);
    }

    public function addMeasurement(Request $request, Practice $practice): JsonResponse
    {
        Gate::authorize('manage', $practice);
        $data = $request->validate([
            'athlete_id' => ['required', 'integer', Rule::in(array_column($this->attendanceList($practice), 'athlete_id'))],
            'metric' => ['required', 'string', 'min:2', 'max:80'],
            'value' => ['required', 'numeric'],
            'unit' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['athlete_id.in' => 'El alumno no pertenece al grupo de esta práctica.']);

        $measurement = Measurement::query()->create($data + [
            'practice_id' => $practice->id,
            'recorded_by' => $this->user($request)->id,
            'recorded_at' => $practice->date.' '.substr($practice->start_time, 0, 5).':00',
        ]);

        return $this->data(new MeasurementResource($measurement->load('athlete')), 201);
    }

    public function addIncident(Request $request, Practice $practice): JsonResponse
    {
        Gate::authorize('manage', $practice);
        $data = $request->validate([
            'athlete_id' => ['nullable', 'integer', Rule::in(array_column($this->attendanceList($practice), 'athlete_id'))],
            'type' => ['required', Rule::in(Incident::TYPES)],
            'severity' => ['required', Rule::in(Incident::SEVERITIES)],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['athlete_id.in' => 'El alumno no pertenece al grupo de esta práctica.']);

        $incident = Incident::query()->create($data + ['practice_id' => $practice->id, 'recorded_by' => $this->user($request)->id]);

        return $this->data(new IncidentResource($incident->load(['athlete', 'recorder'])), 201);
    }

    /**
     * Alumnos activos del grupo de la práctica con su confirmación y asistencia.
     *
     * @return list<array{athlete_id: int, first_name: string, last_name: string, full_name: string, rsvp: string, present: bool|null, comment: string|null}>
     */
    private function attendanceList(Practice $practice): array
    {
        $records = Attendance::query()->where('practice_id', $practice->id)->get()->keyBy('athlete_id');

        return Athlete::query()->active()->where('group_id', $practice->group_id)
            ->orderBy('last_name')->orderBy('first_name')->get()
            ->map(function (Athlete $a) use ($records): array {
                $r = $records->get($a->id);

                return [
                    'athlete_id' => $a->id,
                    'first_name' => $a->first_name,
                    'last_name' => $a->last_name,
                    'full_name' => $a->full_name,
                    'rsvp' => $r->rsvp ?? 'pending',
                    'present' => $r?->present,
                    'comment' => $r?->comment,
                ];
            })->values()->all();
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'group_id' => [$req, 'integer', 'exists:training_groups,id'],
            'coach_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Coach->value)],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'date' => [$req, self::DATE],
            'start_time' => [$req, self::TIME],
            'end_time' => [$req, self::TIME, 'after:start_time'],
            'objectives' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::in(Practice::STATUSES)],
        ];
    }
}
