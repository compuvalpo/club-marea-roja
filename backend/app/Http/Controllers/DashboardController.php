<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\AthleteResource;
use App\Http\Resources\ChargeResource;
use App\Http\Resources\PracticeResource;
use App\Models\Athlete;
use App\Models\Practice;
use App\Services\AccessScope;
use App\Services\FinanceService;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resumen de inicio adaptado al rol del usuario.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly AccessScope $scope,
        private readonly FinanceService $finance,
        private readonly RsvpService $rsvp,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $today = now();

        $upcoming = Practice::query()->with(['group', 'coach', 'venue'])
            ->whereBetween('date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
            ->when($user->role === Role::Coach, fn ($q) => $q->where('coach_id', $user->id))
            ->when($user->hasRole(Role::Guardian, Role::Athlete), fn ($q) => $q->whereIn('group_id', $this->scope->groupIds($user) ?? []))
            ->orderBy('date')->orderBy('start_time')
            ->limit(10)->get();

        if ($user->hasRole(Role::Guardian, Role::Athlete)) {
            $mine = $this->rsvp->myAthletesFor($user, $upcoming);
            $upcoming->each(fn (Practice $p) => $p->setAttribute('my_athletes', $mine[$p->id] ?? []));
        }

        $data = [
            'role' => $user->role->value,
            'upcoming_practices' => PracticeResource::collection($upcoming)->resolve($request),
        ];

        if ($user->isAdmin()) {
            $summary = $this->finance->summary($today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString());
            $data['kpis'] = [
                'active_athletes' => Athlete::query()->active()->count(),
                'month_income' => $summary['income'],
                'month_expense' => $summary['expense'],
                'month_balance' => $summary['balance'],
                'delinquents' => count($this->finance->delinquents()),
                'pending_this_month' => $this->finance->charges(['period' => $today->format('Y-m'), 'status' => 'pending'])->count(),
            ];
        }

        if ($user->hasRole(Role::Guardian, Role::Athlete)) {
            $ids = $this->scope->athleteIds($user) ?? [];
            $pending = $this->finance->charges(['athlete_ids' => $ids, 'status' => 'pending']);
            $data['athletes'] = AthleteResource::collection(Athlete::query()->with(['group', 'guardian'])->whereIn('id', $ids)->get())->resolve($request);
            $data['pending_charges'] = ChargeResource::collection($pending)->resolve($request);
            $data['pending_total'] = array_sum(array_column($data['pending_charges'], 'balance'));
        }

        return $this->data($data);
    }
}
