<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Athlete;
use App\Models\Charge;
use App\Models\FixedExpense;
use App\Models\Payment;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Reglas de negocio financieras: mensualidades, pagos, morosidad, gastos fijos y resumen.
 */
class FinanceService
{
    public const DEFAULT_DUE_DAY = 10;

    public const MONTHLY_FEE_CATEGORY = 'Mensualidades';

    /** Fecha de vencimiento del período, ajustada al último día del mes si corresponde. */
    public static function dueDate(string $period, int $day = self::DEFAULT_DUE_DAY): string
    {
        $first = CarbonImmutable::parse($period.'-01');

        return $first->setDay(max(1, min($day, $first->daysInMonth)))->toDateString();
    }

    /** Días de atraso (0 si está pagado o aún no vence). */
    public static function daysLate(string $status, string $dueDate, CarbonInterface $today): int
    {
        if ($status === 'paid') {
            return 0;
        }
        $due = CarbonImmutable::parse($dueDate)->startOfDay();
        $now = CarbonImmutable::parse($today->toDateString());

        return $now->greaterThan($due) ? (int) $due->diffInDays($now) : 0;
    }

    /**
     * Consulta base de cargos con monto pagado y datos del alumno/apoderado.
     *
     * @return Builder<Charge>
     */
    public function chargesQuery(): Builder
    {
        return Charge::query()
            ->with('athlete.guardian')
            ->withSum('payments as paid_amount', 'amount');
    }

    /**
     * @param  array{period?: string|null, status?: string|null, athlete_ids?: list<int>|null}  $filters
     * @return Collection<int, Charge>
     */
    public function charges(array $filters = []): Collection
    {
        return Charge::query()
            ->select('charges.*')
            ->join('athletes', 'athletes.id', '=', 'charges.athlete_id')
            ->with('athlete.guardian')
            ->withSum('payments as paid_amount', 'amount')
            ->when($filters['period'] ?? null, fn (Builder $q, string $p) => $q->where('charges.period', $p))
            ->when($filters['status'] ?? null, fn (Builder $q, string $s) => $q->where('charges.status', $s))
            ->when(array_key_exists('athlete_ids', $filters) && $filters['athlete_ids'] !== null, fn (Builder $q) => $q->whereIn('charges.athlete_id', $filters['athlete_ids']))
            ->orderByDesc('charges.period')
            ->orderBy('athletes.last_name')
            ->get();
    }

    /**
     * Emite los cargos del período para alumnos activos con mensualidad > 0. Idempotente.
     *
     * @return array{created: int, skipped: int}
     */
    public function generateCharges(string $period, int $dueDay = self::DEFAULT_DUE_DAY): array
    {
        $created = 0;
        $skipped = 0;
        Athlete::query()->active()->where('monthly_fee', '>', 0)->orderBy('id')->each(function (Athlete $athlete) use ($period, $dueDay, &$created, &$skipped): void {
            $charge = Charge::query()->firstOrCreate(
                ['athlete_id' => $athlete->id, 'period' => $period],
                ['amount' => $athlete->monthly_fee, 'due_date' => self::dueDate($period, $dueDay), 'status' => 'pending'],
            );
            $charge->wasRecentlyCreated ? $created++ : $skipped++;
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Registra un pago (total o parcial), crea el ingreso contable y actualiza el estado del cargo.
     *
     * @param  array{charge_id: int, amount: int, paid_at: string, method: string, reference?: string|null}  $data
     */
    public function registerPayment(array $data, int $userId): Charge
    {
        /** @var Charge $charge */
        $charge = $this->chargesQuery()->findOrFail($data['charge_id']);

        if ($charge->status === 'paid') {
            throw new ConflictHttpException('El cargo ya se encuentra pagado.');
        }
        $balance = $charge->balance();
        if ($data['amount'] <= 0 || $data['amount'] > $balance) {
            throw ValidationException::withMessages(['amount' => [sprintf('El monto debe estar entre 1 y %d.', $balance)]]);
        }

        DB::transaction(function () use ($data, $charge, $userId): void {
            $payment = Payment::query()->create([
                'charge_id' => $charge->id,
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'registered_by' => $userId,
            ]);
            Transaction::query()->create([
                'type' => 'income',
                'category' => self::MONTHLY_FEE_CATEGORY,
                'amount' => $data['amount'],
                'occurred_on' => $data['paid_at'],
                'description' => sprintf('Mensualidad %s – %s', $charge->period, $charge->athlete->full_name),
                'payment_id' => $payment->id,
                'period' => $charge->period,
                'created_by' => $userId,
            ]);
            if ($charge->paidAmount() + $data['amount'] >= $charge->amount) {
                $charge->update(['status' => 'paid']);
            }
        });

        /** @var Charge */
        return $this->chargesQuery()->findOrFail($charge->id);
    }

    /**
     * Morosos: cargos impagos vencidos. Con período, todos los impagos de ese mes (aunque no venzan).
     *
     * @return list<Charge>
     */
    public function delinquents(?string $period = null): array
    {
        $today = now();
        $charges = $this->charges(['period' => $period, 'status' => 'pending'])
            ->filter(fn (Charge $c): bool => $period !== null || self::daysLate($c->status, (string) $c->due_date, $today) > 0)
            ->sortByDesc(fn (Charge $c): int => self::daysLate($c->status, (string) $c->due_date, $today))
            ->values();

        return $charges->all();
    }

    /**
     * Registra como egreso los gastos fijos activos del período. Idempotente.
     *
     * @return array{created: int, skipped: int}
     */
    public function applyFixedExpenses(string $period, int $userId): array
    {
        $created = 0;
        $skipped = 0;
        foreach (FixedExpense::query()->where('active', true)->orderBy('id')->get() as $expense) {
            $tx = Transaction::query()->firstOrCreate(
                ['fixed_expense_id' => $expense->id, 'period' => $period],
                [
                    'type' => 'expense',
                    'category' => $expense->category,
                    'amount' => $expense->amount,
                    'occurred_on' => self::dueDate($period, $expense->day_of_month),
                    'description' => 'Gasto fijo: '.$expense->name,
                    'created_by' => $userId,
                ],
            );
            $tx->wasRecentlyCreated ? $created++ : $skipped++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return array{income: int, expense: int, balance: int, by_category: list<array{type: string, category: string, total: int}>, by_month: list<array{month: string, income: int, expense: int}>}
     */
    public function summary(string $from, string $to): array
    {
        $rows = Transaction::query()->whereBetween('occurred_on', [$from, $to])->get(['type', 'category', 'amount', 'occurred_on']);

        return self::summarize($rows->map(fn (Transaction $t): array => $t->only(['type', 'category', 'amount', 'occurred_on']))->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $transactions
     * @return array{income: int, expense: int, balance: int, by_category: list<array{type: string, category: string, total: int}>, by_month: list<array{month: string, income: int, expense: int}>}
     */
    public static function summarize(array $transactions): array
    {
        $income = 0;
        $expense = 0;
        $byCategory = [];
        $byMonth = [];
        foreach ($transactions as $t) {
            $amount = (int) $t['amount'];
            $isIncome = $t['type'] === 'income';
            $isIncome ? $income += $amount : $expense += $amount;

            $key = ($isIncome ? 'income' : 'expense').'|'.$t['category'];
            $byCategory[$key] = ($byCategory[$key] ?? 0) + $amount;
            $month = substr((string) $t['occurred_on'], 0, 7);
            $byMonth[$month] ??= ['month' => $month, 'income' => 0, 'expense' => 0];
            $byMonth[$month][$isIncome ? 'income' : 'expense'] += $amount;
        }
        ksort($byMonth);
        arsort($byCategory);

        $categories = [];
        foreach ($byCategory as $key => $total) {
            [$type, $category] = explode('|', (string) $key, 2);
            $categories[] = ['type' => $type, 'category' => $category, 'total' => $total];
        }

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'by_category' => $categories,
            'by_month' => array_values($byMonth),
        ];
    }
}
