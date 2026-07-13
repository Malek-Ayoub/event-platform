<?php

namespace App\Services\Reports\Queries;

use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Services\Reports\Queries\Concerns\AppliesReportDateRange;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Facades\DB;

class PlatformRevenueQuery
{
    use AppliesReportDateRange;

    /**
     * @return array<string, mixed>
     */
    public function execute(SettlementDateRange $range): array
    {
        $ordersQuery = DB::table('orders')
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Refunded->value]);

        $this->applyDateRange($ordersQuery, 'updated_at', $range);

        $ordersCount = (int) (clone $ordersQuery)->count();
        $grossRevenue = $this->formatAmount((clone $ordersQuery)->sum('total'));

        $ticketsQuery = DB::table('tickets')
            ->whereNotIn('status', [
                TicketStatus::Cancelled->value,
                TicketStatus::Invalidated->value,
            ]);

        $this->applyDateRange($ticketsQuery, 'issued_at', $range);

        $activeVenuesQuery = DB::table('orders')
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Refunded->value]);

        $this->applyDateRange($activeVenuesQuery, 'updated_at', $range);

        $currency = DB::table('payment_transactions')
            ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
            ->whereIn('orders.status', [OrderStatus::Paid->value, OrderStatus::Refunded->value])
            ->orderByDesc('orders.id')
            ->value('payment_transactions.currency');

        return [
            'gross_revenue' => $grossRevenue,
            'orders_count' => $ordersCount,
            'tickets_sold' => (int) $ticketsQuery->count(),
            'active_venues' => (int) (clone $activeVenuesQuery)->distinct()->count('venue_id'),
            'currency' => $currency !== null ? (string) $currency : 'USD',
        ];
    }
}
