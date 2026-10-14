<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\GroupResource;
use App\Http\Resources\VenueResource;
use App\Models\TrainingGroup;
use App\Models\Venue;
use App\Services\AccessScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Recintos (lugares de entrenamiento y de eventos) y grupos de entrenamiento.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly AccessScope $scope) {}

    public function venues(): AnonymousResourceCollection
    {
        return VenueResource::collection(Venue::query()->orderByDesc('is_training_venue')->orderBy('name')->get());
    }

    public function storeVenue(Request $request): JsonResponse
    {
        return $this->data(new VenueResource(Venue::query()->create($request->validate($this->venueRules()))), 201);
    }

    public function updateVenue(Request $request, Venue $venue): JsonResponse
    {
        $venue->update($request->validate($this->venueRules(true)));

        return $this->data(new VenueResource($venue));
    }

    public function destroyVenue(Venue $venue): JsonResponse
    {
        $venue->delete();

        return $this->noContent();
    }

    public function groups(Request $request): AnonymousResourceCollection
    {
        $ids = $this->scope->groupIds($this->user($request));
        $groups = TrainingGroup::query()
            ->with('coach')
            ->withCount(['athletes' => fn ($q) => $q->where('active', true)])
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();

        return GroupResource::collection($groups);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $group = TrainingGroup::query()->create($request->validate($this->groupRules()));

        return $this->data(new GroupResource($group->load('coach')), 201);
    }

    public function updateGroup(Request $request, TrainingGroup $group): JsonResponse
    {
        $group->update($request->validate($this->groupRules(true)));

        return $this->data(new GroupResource($group->load('coach')));
    }

    public function destroyGroup(TrainingGroup $group): JsonResponse
    {
        $group->delete();

        return $this->noContent();
    }

    /** @return array<string, mixed> */
    private function venueRules(bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'min:2', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_training_venue' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, mixed> */
    private function groupRules(bool $partial = false): array
    {
        return [
            'name' => [$partial ? 'sometimes' : 'required', 'string', 'min:2', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:40'],
            'coach_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', Role::Coach->value)],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
