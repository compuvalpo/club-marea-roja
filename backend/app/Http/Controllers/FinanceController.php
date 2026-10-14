<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\ChargeResource;
use App\Http\Resources\FixedExpenseResource;
use App\Http\Resources\TransactionResource;
use App\Models\FixedExpense;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\AccessScope;
use App\Services\FinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Finanzas: mensualidades, pagos, morosos, ingresos/egresos y gastos fijos.
 */
class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $finance,
        private readonly AccessScope $scope,
    ) {}

    public function charges(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['period' => ['nullable', self::PERIOD], 'status' => ['nullable', Rule::in(['pending', 'paid'])]]);

        return ChargeResource::collection($this->finance->charges($q));
    }

    /** Cargos y pagos de los pupilos (apoderado) o propios (alumno). */
    public function myCharges(Request $request): AnonymousResourceCollection
    {
        $charges = $this->finance->charges(['athlete_ids' => $this->scope->athleteIds($this->user($request)) ?? []]);
        $charges->load('payments');

        return ChargeResource::collection($charges);
    }

    public function generateCharges(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', self::PERIOD], 'due_day' => ['nullable', 'integer', 'min:1', 'max:28']]);

        return $this->data($this->finance->generateCharges($data['period'], (int) ($data['due_day'] ?? FinanceService::DEFAULT_DUE_DAY)), 201);
    }

    public function registerPayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'charge_id' => ['required', 'integer', 'exists:charges,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', self::DATE],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->data(new ChargeResource($this->finance->registerPayment($data, $this->user($request)->id)), 201);
    }

    public function delinquents(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['period' => ['nullable', self::PERIOD]]);

        return ChargeResource::collection($this->finance->delinquents($q['period'] ?? null));
    }

    public function summary(Request $request): JsonResponse
    {
        $q = $request->validate(['from' => ['nullable', self::DATE], 'to' => ['nullable', self::DATE]]);
        $from = $q['from'] ?? now()->startOfYear()->toDateString();
        $to = $q['to'] ?? now()->endOfMonth()->toDateString();

        return $this->data(['from' => $from, 'to' => $to] + $this->finance->summary($from, $to));
    }

    public function transactions(Request $request): AnonymousResourceCollection
    {
        $q = $request->validate(['from' => ['nullable', self::DATE], 'to' => ['nullable', self::DATE], 'type' => ['nullable', Rule::in(['income', 'expense'])]]);

        return TransactionResource::collection(Transaction::query()
            ->when($q['from'] ?? null, fn ($b, $v) => $b->where('occurred_on', '>=', $v))
            ->when($q['to'] ?? null, fn ($b, $v) => $b->where('occurred_on', '<=', $v))
            ->when($q['type'] ?? null, fn ($b, $v) => $b->where('type', $v))
            ->orderByDesc('occurred_on')->orderByDesc('id')
            ->get());
    }

    public function storeTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'min:2', 'max:60'],
            'amount' => ['required', 'integer', 'min:1'],
            'occurred_on' => ['required', self::DATE],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $tx = Transaction::query()->create($data + ['created_by' => $this->user($request)->id]);

        return $this->data(new TransactionResource($tx), 201);
    }

    public function destroyTransaction(Transaction $transaction): JsonResponse
    {
        if ($transaction->payment_id !== null) {
            throw new ConflictHttpException('Los ingresos por mensualidad se anulan desde el pago asociado.');
        }
        $transaction->delete();

        return $this->noContent();
    }

    public function fixedExpenses(): AnonymousResourceCollection
    {
        return FixedExpenseResource::collection(FixedExpense::query()->orderBy('name')->get());
    }

    public function storeFixedExpense(Request $request): JsonResponse
    {
        $expense = FixedExpense::query()->create($request->validate($this->fixedRules()) + ['active' => true]);

        return $this->data(new FixedExpenseResource($expense->refresh()), 201);
    }

    public function updateFixedExpense(Request $request, FixedExpense $fixedExpense): JsonResponse
    {
        $fixedExpense->update($request->validate($this->fixedRules(true)));

        return $this->data(new FixedExpenseResource($fixedExpense));
    }

    public function destroyFixedExpense(FixedExpense $fixedExpense): JsonResponse
    {
        $fixedExpense->delete();

        return $this->noContent();
    }

    public function applyFixedExpenses(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['required', self::PERIOD]]);

        return $this->data($this->finance->applyFixedExpenses($data['period'], $this->user($request)->id), 201);
    }

    /** @return array<string, mixed> */
    private function fixedRules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'min:2', 'max:120'],
            'category' => [$req, 'string', 'max:60'],
            'amount' => [$req, 'integer', 'min:1'],
            'day_of_month' => [$req, 'integer', 'min:1', 'max:31'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
