<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class FinanceServiceTest extends TestCase
{
    public function test_due_date_is_clamped_to_month_length(): void
    {
        self::assertSame('2026-10-10', FinanceService::dueDate('2026-10'));
        self::assertSame('2027-02-28', FinanceService::dueDate('2027-02', 31));
    }

    public function test_days_late(): void
    {
        $today = CarbonImmutable::parse('2026-10-15 18:00');

        self::assertSame(5, FinanceService::daysLate('pending', '2026-10-10', $today));
        self::assertSame(0, FinanceService::daysLate('pending', '2026-10-20', $today));
        self::assertSame(0, FinanceService::daysLate('paid', '2026-09-10', $today));
    }

    public function test_summarize_groups_by_category_and_month(): void
    {
        $summary = FinanceService::summarize([
            ['type' => 'income', 'category' => 'Mensualidades', 'amount' => 30000, 'occurred_on' => '2026-09-05'],
            ['type' => 'income', 'category' => 'Mensualidades', 'amount' => 35000, 'occurred_on' => '2026-10-05'],
            ['type' => 'expense', 'category' => 'Arriendos', 'amount' => 20000, 'occurred_on' => '2026-10-06'],
        ]);

        self::assertSame(65000, $summary['income']);
        self::assertSame(20000, $summary['expense']);
        self::assertSame(45000, $summary['balance']);
        self::assertSame(['type' => 'income', 'category' => 'Mensualidades', 'total' => 65000], $summary['by_category'][0]);
        self::assertSame([
            ['month' => '2026-09', 'income' => 30000, 'expense' => 0],
            ['month' => '2026-10', 'income' => 35000, 'expense' => 20000],
        ], $summary['by_month']);
    }
}
