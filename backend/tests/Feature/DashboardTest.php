<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class DashboardTest extends TestCase
{
    public function test_admin_dashboard_has_financial_kpis(): void
    {
        $data = $this->api('GET', '/api/v1/dashboard', token: $this->admin())->json('data');

        self::assertSame(9, $data['kpis']['active_athletes']);
        self::assertSame(1, $data['kpis']['delinquents']);
        self::assertNotEmpty($data['upcoming_practices']);
    }

    public function test_guardian_dashboard_includes_children_debt_and_rsvp(): void
    {
        $data = $this->api('GET', '/api/v1/dashboard', token: $this->guardian())->json('data');

        self::assertArrayNotHasKey('kpis', $data);
        self::assertCount(2, $data['athletes']);
        self::assertSame(65000, $data['pending_total']);
        self::assertArrayHasKey('my_athletes', $data['upcoming_practices'][0]);
    }

    public function test_coach_dashboard_only_lists_own_practices(): void
    {
        $data = $this->api('GET', '/api/v1/dashboard', token: $this->coach())->json('data');

        self::assertSame(['Andrés Rojas'], array_values(array_unique(array_column($data['upcoming_practices'], 'coach_name'))));
    }
}
