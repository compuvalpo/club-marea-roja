<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FinanceTest extends TestCase
{
    public function test_generate_charges_is_idempotent(): void
    {
        $token = $this->admin();
        $this->api('POST', '/api/v1/finance/charges/generate', ['period' => '2026-11'], $token)->assertCreated()->assertJsonPath('data.created', 9);
        $this->api('POST', '/api/v1/finance/charges/generate', ['period' => '2026-11'], $token)->assertJsonPath('data', ['created' => 0, 'skipped' => 9]);
    }

    public function test_partial_then_full_payment_marks_charge_as_paid_and_creates_income(): void
    {
        $token = $this->admin();
        $this->api('POST', '/api/v1/finance/charges/generate', ['period' => '2026-11'], $token);
        $charge = $this->api('GET', '/api/v1/finance/charges?period=2026-11', token: $token)->json('data.0');

        $partial = $this->api('POST', '/api/v1/finance/payments', ['charge_id' => $charge['id'], 'amount' => 10000, 'paid_at' => '2026-11-03', 'method' => 'cash'], $token)->json('data');
        self::assertSame('pending', $partial['status']);
        self::assertSame($charge['amount'] - 10000, $partial['balance']);

        $this->api('POST', '/api/v1/finance/payments', ['charge_id' => $charge['id'], 'amount' => $partial['balance'], 'paid_at' => '2026-11-04', 'method' => 'transfer', 'reference' => 'TRX-991'], $token)
            ->assertCreated()->assertJsonPath('data.status', 'paid');

        $income = $this->api('GET', '/api/v1/finance/transactions?from=2026-11-01&to=2026-11-30&type=income', token: $token)->json('data');
        self::assertSame($charge['amount'], array_sum(array_column($income, 'amount')));
    }

    public function test_overpayment_and_paying_paid_charge_are_rejected(): void
    {
        $token = $this->admin();
        $paid = $this->api('GET', '/api/v1/finance/charges?status=paid', token: $token)->json('data.0');
        $this->api('POST', '/api/v1/finance/payments', ['charge_id' => $paid['id'], 'amount' => 1000, 'paid_at' => '2026-10-05', 'method' => 'cash'], $token)->assertStatus(409);

        $pending = $this->api('GET', '/api/v1/finance/charges?status=pending', token: $token)->json('data.0');
        $this->api('POST', '/api/v1/finance/payments', ['charge_id' => $pending['id'], 'amount' => $pending['amount'] + 1, 'paid_at' => '2026-10-05', 'method' => 'cash'], $token)->assertStatus(422);
    }

    public function test_delinquents_lists_overdue_charges_sorted_by_delay(): void
    {
        $token = $this->admin();
        $overdue = $this->api('GET', '/api/v1/finance/delinquents', token: $token)->json('data');
        self::assertNotEmpty($overdue);
        self::assertTrue($overdue[0]['is_overdue']);
        self::assertSame('2026-09', $overdue[0]['period']);

        // Para el mes en curso se listan todos los impagos aunque aún no venzan (vence el 10).
        $month = $this->api('GET', '/api/v1/finance/delinquents?period=2026-10', token: $token)->json('data');
        self::assertNotEmpty($month);
        self::assertFalse($month[0]['is_overdue']);
    }

    public function test_fixed_expenses_are_applied_once_per_period(): void
    {
        $token = $this->admin();
        $this->api('POST', '/api/v1/finance/fixed-expenses/apply', ['period' => '2026-11'], $token)->assertJsonPath('data', ['created' => 3, 'skipped' => 0]);
        $this->api('POST', '/api/v1/finance/fixed-expenses/apply', ['period' => '2026-11'], $token)->assertJsonPath('data', ['created' => 0, 'skipped' => 3]);
    }

    public function test_summary_balances_income_and_expenses(): void
    {
        $summary = $this->api('GET', '/api/v1/finance/summary?from=2026-01-01&to=2026-12-31', token: $this->admin())->json('data');

        self::assertSame($summary['income'] - $summary['expense'], $summary['balance']);
        self::assertNotEmpty($summary['by_month']);
    }

    public function test_guardian_sees_only_own_charges_with_payments(): void
    {
        $charges = $this->api('GET', '/api/v1/me/charges', token: $this->guardian())->json('data');

        self::assertNotEmpty($charges);
        self::assertEqualsCanonicalizing([1, 2], array_values(array_unique(array_column($charges, 'athlete_id'))));
        self::assertArrayHasKey('payments', $charges[0]);
    }

    public function test_monthly_fee_income_cannot_be_deleted_directly(): void
    {
        $token = $this->admin();
        $fee = collect($this->api('GET', '/api/v1/finance/transactions?type=income', token: $token)->json('data'))->firstWhere('payment_id', '!=', null);

        $this->api('DELETE', '/api/v1/finance/transactions/'.$fee['id'], token: $token)->assertStatus(409);
    }

    public function test_period_format_is_validated(): void
    {
        $this->api('POST', '/api/v1/finance/charges/generate', ['period' => '2026-13'], $this->admin())
            ->assertStatus(422)->assertJsonPath('error.errors.period.0', 'Debe tener formato AAAA-MM.');
    }
}
