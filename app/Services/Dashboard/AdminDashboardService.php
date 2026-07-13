<?php

namespace App\Services\Dashboard;

use App\Services\Dashboard\Data\AdminDashboardData;
use App\Services\Dashboard\Queries\Admin\AdminDashboardAlertsQuery;
use App\Services\Dashboard\Queries\Admin\AdminDashboardKpiQuery;
use App\Services\Dashboard\Queries\Admin\AdminDashboardLatestCheckInsQuery;
use App\Services\Dashboard\Queries\Admin\AdminDashboardLatestOrdersQuery;
use App\Services\Dashboard\Queries\Admin\AdminDashboardLatestPaymentsQuery;
use App\Services\Dashboard\Queries\Admin\AdminDashboardTodayQuery;
use App\Services\Reports\Queries\PlatformRevenueQuery;
use App\Services\Reports\Queries\TopEventsQuery;
use App\Services\Reports\Queries\TopVenuesQuery;
use App\Services\Settlements\Data\SettlementDateRange;

class AdminDashboardService
{
    private const TOP_LIMIT = 5;

    private const LATEST_LIMIT = 5;

    public function __construct(
        private AdminDashboardKpiQuery $kpiQuery,
        private AdminDashboardTodayQuery $todayQuery,
        private TopVenuesQuery $topVenuesQuery,
        private TopEventsQuery $topEventsQuery,
        private AdminDashboardLatestOrdersQuery $latestOrdersQuery,
        private AdminDashboardLatestPaymentsQuery $latestPaymentsQuery,
        private AdminDashboardLatestCheckInsQuery $latestCheckInsQuery,
        private AdminDashboardAlertsQuery $alertsQuery,
        private PlatformRevenueQuery $platformRevenueQuery,
    ) {}

    public function build(): AdminDashboardData
    {
        $allTime = new SettlementDateRange;
        $platform = $this->platformRevenueQuery->execute($allTime);

        return new AdminDashboardData(
            kpis: $this->kpiQuery->execute(),
            today: $this->todayQuery->execute(),
            topVenues: $this->topVenuesQuery->execute($allTime, self::TOP_LIMIT),
            topEvents: $this->topEventsQuery->execute($allTime, self::TOP_LIMIT),
            latestOrders: $this->latestOrdersQuery->execute(self::LATEST_LIMIT),
            latestPayments: $this->latestPaymentsQuery->execute(self::LATEST_LIMIT),
            latestCheckIns: $this->latestCheckInsQuery->execute(self::LATEST_LIMIT),
            alerts: $this->alertsQuery->execute(),
            meta: [
                'currency' => (string) $platform['currency'],
                'generated_at' => now()->toIso8601String(),
            ],
        );
    }
}
