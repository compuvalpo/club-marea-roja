<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\StatsService;
use PHPUnit\Framework\TestCase;

final class StatsServiceTest extends TestCase
{
    public function test_best_time_is_the_lowest_and_best_distance_the_highest(): void
    {
        $metrics = StatsService::groupMetrics([
            ['metric' => '50 m libre', 'unit' => 's', 'value' => '34.2', 'recorded_at' => '2026-09-01 18:00:00'],
            ['metric' => '50 m libre', 'unit' => 's', 'value' => '33.1', 'recorded_at' => '2026-09-08 18:00:00'],
            ['metric' => '50 m libre', 'unit' => 's', 'value' => '33.5', 'recorded_at' => '2026-09-15 18:00:00'],
            ['metric' => 'Salto largo', 'unit' => 'm', 'value' => '3.1', 'recorded_at' => '2026-09-01 18:00:00'],
            ['metric' => 'Salto largo', 'unit' => 'm', 'value' => '3.4', 'recorded_at' => '2026-09-08 18:00:00'],
        ]);

        self::assertCount(2, $metrics);
        self::assertSame(33.1, $metrics[0]['best']);
        self::assertSame(33.5, $metrics[0]['last']);
        self::assertSame(3, $metrics[0]['count']);
        self::assertSame(3.4, $metrics[1]['best']);
        self::assertSame(['date' => '2026-09-01', 'value' => 34.2], $metrics[0]['series'][0]);
    }
}
