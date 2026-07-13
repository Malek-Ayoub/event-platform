<?php

namespace App\Services\Dashboard\Queries;

use App\Services\Reports\Data\OrganizerReportFilter;
use App\Services\Reports\OrganizerReportService;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Carbon;

class DashboardTodayQuery
{
    public function __construct(
        private OrganizerReportService $organizerReportService,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function execute(int $venueId, ?Carbon $asOf = null): array
    {
        $moment = ($asOf ?? now())->copy();
        $range = new SettlementDateRange(
            from: $moment->copy()->startOfDay(),
            to: $moment->copy()->endOfDay(),
        );

        $report = $this->organizerReportService->build(new OrganizerReportFilter($venueId, $range));

        return [
            'today_sales' => $report->sales['gross_sales'],
            'today_orders' => $report->sales['orders_count'],
            'today_check_ins' => $report->attendance['checked_in'],
            'today_revenue' => $report->revenue['net_revenue'],
        ];
    }
}
