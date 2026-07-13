<?php

namespace App\Services\Dashboard;

use App\Services\Dashboard\Data\OrganizerDashboardData;
use App\Services\Dashboard\Queries\DashboardKpiQuery;
use App\Services\Dashboard\Queries\DashboardLatestCheckInsQuery;
use App\Services\Dashboard\Queries\DashboardLatestOrdersQuery;
use App\Services\Dashboard\Queries\DashboardTodayQuery;
use App\Services\Dashboard\Queries\DashboardUpcomingEventsQuery;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\SettlementSummaryService;

class OrganizerDashboardService
{
    private const UPCOMING_EVENTS_LIMIT = 5;

    private const LATEST_ACTIVITY_LIMIT = 5;

    public function __construct(
        private DashboardKpiQuery $kpiQuery,
        private DashboardTodayQuery $todayQuery,
        private DashboardUpcomingEventsQuery $upcomingEventsQuery,
        private DashboardLatestOrdersQuery $latestOrdersQuery,
        private DashboardLatestCheckInsQuery $latestCheckInsQuery,
        private SettlementSummaryService $settlementSummaryService,
    ) {}

    public function build(int $venueId): OrganizerDashboardData
    {
        $summary = $this->settlementSummaryService->summarize($venueId, new SettlementDateRange);

        return new OrganizerDashboardData(
            kpis: $this->kpiQuery->execute($venueId),
            today: $this->todayQuery->execute($venueId),
            events: $this->upcomingEventsQuery->execute($venueId, self::UPCOMING_EVENTS_LIMIT),
            latestOrders: $this->latestOrdersQuery->execute($venueId, self::LATEST_ACTIVITY_LIMIT),
            latestCheckIns: $this->latestCheckInsQuery->execute($venueId, self::LATEST_ACTIVITY_LIMIT),
            commission: [
                'due' => $summary->commissionDue,
                'paid' => $summary->commissionPaid,
                'outstanding' => $summary->commissionOutstanding,
            ],
            meta: [
                'currency' => $summary->currency,
                'generated_at' => now()->toIso8601String(),
            ],
        );
    }
}
