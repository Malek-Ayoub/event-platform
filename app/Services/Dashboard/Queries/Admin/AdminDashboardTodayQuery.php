<?php

namespace App\Services\Dashboard\Queries\Admin;

use App\Services\Reports\Queries\PlatformRevenueQuery;
use App\Services\Reports\Queries\RefundReportQuery;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardTodayQuery
{
    public function __construct(
        private PlatformRevenueQuery $platformRevenueQuery,
        private RefundReportQuery $refundReportQuery,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function execute(?Carbon $asOf = null): array
    {
        $moment = ($asOf ?? now())->copy();
        $range = new SettlementDateRange(
            from: $moment->copy()->startOfDay(),
            to: $moment->copy()->endOfDay(),
        );

        $platform = $this->platformRevenueQuery->execute($range);
        $refunds = $this->refundReportQuery->execute(
            $range,
            (int) $platform['orders_count'],
            (string) $platform['gross_revenue'],
        );

        $checkIns = (int) DB::table('ticket_check_ins')
            ->where('checked_in_at', '>=', $range->from)
            ->where('checked_in_at', '<=', $range->to)
            ->count();

        $eventsStartingToday = (int) DB::table('events')
            ->whereNull('deleted_at')
            ->whereBetween('start_datetime', [$range->from, $range->to])
            ->count();

        return [
            'today_sales' => (string) $platform['gross_revenue'],
            'today_revenue' => $this->subtractAmounts(
                (string) $platform['gross_revenue'],
                (string) $refunds['refunded_amount'],
            ),
            'today_orders' => (int) $platform['orders_count'],
            'today_check_ins' => $checkIns,
            'events_starting_today' => $eventsStartingToday,
        ];
    }

    private function subtractAmounts(string $left, string $right): string
    {
        return bcsub(
            number_format((float) $left, 2, '.', ''),
            number_format((float) $right, 2, '.', ''),
            2,
        );
    }
}
