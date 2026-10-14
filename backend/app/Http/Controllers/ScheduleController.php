<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Horarios semanales recurrentes (Admin) y generación de prácticas.
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return ScheduleResource::collection(
            Schedule::query()->with(['group', 'coach', 'venue'])->orderBy('weekday')->orderBy('start_time')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $schedule = Schedule::query()->create($request->validate($this->rules()));

        return $this->data(new ScheduleResource($schedule->load(['group', 'coach', 'venue'])), 201);
    }

    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $request->mergeIfMissing(['start_time' => $schedule->start_time, 'end_time' => $schedule->end_time]);
        $schedule->update($request->validate($this->rules(true)));

        return $this->data(new ScheduleResource($schedule->load(['group', 'coach', 'venue'])));
    }

    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();

        return $this->noContent();
    }

    public function generate(Request $request, Schedule $schedule): JsonResponse
    {
        $data = $request->validate(['from' => ['required', self::DATE], 'to' => ['required', self::DATE]]);

        return $this->data($this->service->generate($schedule, $data['from'], $data['to']), 201);
    }

    /** @return array<string, mixed> */
    private function rules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'group_id' => [$req, 'integer', 'exists:training_groups,id'],
            'coach_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Coach->value)],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'weekday' => [$req, 'integer', 'min:1', 'max:7'],
            'start_time' => [$req, self::TIME],
            'end_time' => [$req, self::TIME, 'after:start_time'],
            'valid_from' => [$req, self::DATE],
            'valid_to' => ['nullable', self::DATE],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
