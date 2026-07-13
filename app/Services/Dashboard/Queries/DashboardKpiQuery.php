<?php

namespace App\Services\Dashboard\Queries;

use App\Services\Reports\Data\OrganizerReportFilter;
use App\Services\Reports\OrganizerReportService;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\SettlementSummaryService;
use Illuminate\Support\Facades\DB;

class DashboardKpiQuery
{
    public function __construct(
        private OrganizerReportService $organizerReportService,
        private SettlementSummaryService $settlementSummaryService,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function execute(int $venueId): array
    {
        $allTime = new SettlementDateRange;
        $report = $this->organizerReportService->build(new OrganizerReportFilter($venueId, $allTime));
        $summary = $this->settlementSummaryService->summarize($venueId, $allTime);

        return [
            'gross_sales' => $report->sales['gross_sales'],
            'net_revenue' => $report->revenue['net_revenue'],
            'orders_count' => $report->sales['orders_count'],
            'tickets_sold' => $report->sales['tickets_sold'],
            'tickets_remaining' => $this->ticketsRemaining($venueId),
            'attendance_rate' => $report->attendance['attendance_rate'],
            'outstanding_commission' => $summary->commissionOutstanding,
        ];
    }

    private function ticketsRemaining(int $venueId): int
    {
        $remaining = DB::table('ticket_types')
            ->where('venue_id', $venueId)
            ->selectRaw('COALESCE(SUM(CASE WHEN quantity > quantity_sold THEN quantity - quantity_sold ELSE 0 END), 0) as remaining')
            ->value('remaining');

        return (int) $remaining;
    }
}
