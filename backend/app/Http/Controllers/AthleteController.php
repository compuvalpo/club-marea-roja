<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\AthleteResource;
use App\Models\Athlete;
use App\Services\AccessScope;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AthleteController extends Controller
{
    public function __construct(
        private readonly AccessScope $scope,
        private readonly StatsService $stats,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $ids = $this->scope->athleteIds($this->user($request));
        $athletes = Athlete::query()
            ->with(['group', 'guardian'])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->when($request->integer('group_id'), fn ($q, int $g) => $q->where('group_id', $g))
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        return AthleteResource::collection($athletes);
    }

    public function show(Athlete $athlete): JsonResponse
    {
        Gate::authorize('view', $athlete);

        return $this->data(new AthleteResource($athlete->load(['group', 'guardian'])));
    }

    public function store(Request $request): JsonResponse
    {
        $athlete = Athlete::query()->create($request->validate($this->rules()) + ['active' => true]);

        return $this->data(new AthleteResource($athlete->load(['group', 'guardian'])), 201);
    }

    public function update(Request $request, Athlete $athlete): JsonResponse
    {
        $athlete->update($request->validate($this->rules($athlete)));

        return $this->data(new AthleteResource($athlete->load(['group', 'guardian'])));
    }

    public function destroy(Athlete $athlete): JsonResponse
    {
        $athlete->delete();

        return $this->noContent();
    }

    public function stats(Athlete $athlete): JsonResponse
    {
        Gate::authorize('view', $athlete);

        return $this->data(['athlete' => new AthleteResource($athlete->load(['group', 'guardian']))] + $this->stats->forAthlete($athlete));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?Athlete $athlete = null): array
    {
        $req = $athlete ? 'sometimes' : 'required';

        return [
            'first_name' => [$req, 'string', 'min:2', 'max:80'],
            'last_name' => [$req, 'string', 'min:2', 'max:80'],
            'birth_date' => ['nullable', self::DATE],
            'group_id' => ['nullable', 'integer', 'exists:training_groups,id'],
            'guardian_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Guardian->value)],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Athlete->value), Rule::unique('athletes', 'user_id')->ignore($athlete?->id)],
            'monthly_fee' => [$req, 'integer', 'min:0', 'max:10000000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
