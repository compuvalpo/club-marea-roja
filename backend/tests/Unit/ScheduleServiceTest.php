<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ScheduleService;
use PHPUnit\Framework\TestCase;

final class ScheduleServiceTest extends TestCase
{
    public function test_lists_weekly_occurrences_in_range(): void
    {
        // Octubre 2026: los lunes son 5, 12, 19 y 26.
        self::assertSame(
            ['2026-10-05', '2026-10-12', '2026-10-19', '2026-10-26'],
            ScheduleService::occurrences(1, '2026-10-01', '2026-10-31'),
        );
    }

    public function test_respects_schedule_validity(): void
    {
        self::assertSame(['2026-10-14', '2026-10-21'], ScheduleService::occurrences(3, '2026-10-01', '2026-10-31', '2026-10-10', '2026-10-21'));
    }

    public function test_returns_empty_when_validity_outside_range(): void
    {
        self::assertSame([], ScheduleService::occurrences(5, '2026-10-01', '2026-10-31', '2026-11-01'));
    }

    public function test_sunday_is_seven(): void
    {
        self::assertSame(['2026-10-04'], ScheduleService::occurrences(7, '2026-10-01', '2026-10-10'));
    }

    public function test_rejects_invalid_weekday(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ScheduleService::occurrences(0, '2026-10-01', '2026-10-10');
    }
}
