<?php

namespace Tests\Unit\Services\Dashboard;

use App\Services\Dashboard\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_zero_defaults_for_empty_platform(): void
    {
        $dashboard = app(AdminDashboardService::class)->build();

        $this->assertSame('0.00', $dashboard->kpis['gross_revenue']);
        $this->assertSame('0.00', $dashboard->kpis['net_revenue']);
        $this->assertSame(0, $dashboard->kpis['active_events']);
        $this->assertSame([], $dashboard->topVenues);
        $this->assertSame([], $dashboard->latestOrders);
        $this->assertCount(3, $dashboard->alerts);
        $this->assertSame(0, $dashboard->alerts[0]['count']);
        $this->assertSame('USD', $dashboard->meta['currency']);
    }
}
