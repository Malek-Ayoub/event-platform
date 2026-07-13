<?php

namespace App\Services\Reports;

use App\Services\Reports\Data\AdminReportData;
use App\Services\Reports\Data\AdminReportFilter;
use App\Services\Reports\Queries\CommissionReportQuery;
use App\Services\Reports\Queries\PaymentMethodReportQuery;
use App\Services\Reports\Queries\PlatformRevenueQuery;
use App\Services\Reports\Queries\RefundReportQuery;
use App\Services\Reports\Queries\TopEventsQuery;
use App\Services\Reports\Queries\TopVenuesQuery;

class AdminReportService
{
    public function __construct(
        private PlatformRevenueQuery $platformRevenueQuery,
        private CommissionReportQuery $commissionReportQuery,
        private TopVenuesQuery $topVenuesQuery,
        private TopEventsQuery $topEventsQuery,
        private PaymentMethodReportQuery $paymentMethodReportQuery,
        private RefundReportQuery $refundReportQuery,
    ) {}

    public function build(AdminReportFilter $filter): AdminReportData
    {
        $platformMetrics = $this->platformRevenueQuery->execute($filter->range);
        $refunds = $this->refundReportQuery->execute(
            $filter->range,
            (int) $platformMetrics['orders_count'],
            (string) $platformMetrics['gross_revenue'],
        );

        $platform = [
            'gross_revenue' => $platformMetrics['gross_revenue'],
            'net_revenue' => $this->subtractAmounts(
                (string) $platformMetrics['gross_revenue'],
                (string) $refunds['refunded_amount'],
            ),
            'orders_count' => $platformMetrics['orders_count'],
            'tickets_sold' => $platformMetrics['tickets_sold'],
            'active_venues' => $platformMetrics['active_venues'],
        ];

        return new AdminReportData(
            platform: $platform,
            commissions: $this->commissionReportQuery->execute($filter->range),
            topVenues: $this->topVenuesQuery->execute($filter->range, $filter->limit),
            topEvents: $this->topEventsQuery->execute($filter->range, $filter->limit),
            paymentMethods: $this->paymentMethodReportQuery->execute($filter->range),
            refunds: $refunds,
            meta: [
                'from' => $filter->range->from?->toIso8601String(),
                'to' => $filter->range->to?->toIso8601String(),
                'currency' => $platformMetrics['currency'],
                'limit' => $filter->limit,
            ],
        );
    }

    private function subtractAmounts(string $left, string $right): string
    {
        return bcsub(number_format((float) $left, 2, '.', ''), number_format((float) $right, 2, '.', ''), 2);
    }
}
