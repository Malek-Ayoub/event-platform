<?php

namespace App\Services\Dashboard\Queries\Admin;

use App\Enums\EventDomain\EventStatus;
use App\Services\Reports\Queries\CommissionReportQuery;
use App\Services\Reports\Queries\PlatformRevenueQuery;
use App\Services\Reports\Queries\RefundReportQuery;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Facades\DB;

class AdminDashboardKpiQuery
{
    public function __construct(
        private PlatformRevenueQuery $platformRevenueQuery,
        private CommissionReportQuery $commissionReportQuery,
        private RefundReportQuery $refundReportQuery,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function execute(): array
    {
        $range = new SettlementDateRange;
        $platform = $this->platformRevenueQuery->execute($range);
        $refunds = $this->refundReportQuery->execute(
            $range,
            (int) $platform['orders_count'],
            (string) $platform['gross_revenue'],
        );
        $commissions = $this->commissionReportQuery->execute($range);

        $activeEvents = (int) DB::table('events')
            ->whereNull('deleted_at')
            ->where('status', EventStatus::Published->value)
            ->count();

        return [
            'gross_revenue' => (string) $platform['gross_revenue'],
            'net_revenue' => $this->subtractAmounts(
                (string) $platform['gross_revenue'],
                (string) $refunds['refunded_amount'],
            ),
            'commission_due' => (string) $commissions['commission_due'],
            'commission_paid' => (string) $commissions['commission_paid'],
            'outstanding_commission' => (string) $commissions['outstanding_commission'],
            'active_events' => $activeEvents,
            'active_venues' => (int) $platform['active_venues'],
            'orders_count' => (int) $platform['orders_count'],
            'tickets_sold' => (int) $platform['tickets_sold'],
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
