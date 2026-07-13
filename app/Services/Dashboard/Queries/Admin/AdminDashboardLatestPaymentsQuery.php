<?php

namespace App\Services\Dashboard\Queries\Admin;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardLatestPaymentsQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $limit = 5): array
    {
        $rows = DB::table('payment_transactions')
            ->join('venues', 'venues.id', '=', 'payment_transactions.venue_id')
            ->join('orders', 'orders.id', '=', 'payment_transactions.order_id')
            ->whereIn('payment_transactions.status', [
                PaymentTransactionStatus::Paid->value,
                PaymentTransactionStatus::Completed->value,
            ])
            ->orderByDesc('payment_transactions.updated_at')
            ->orderByDesc('payment_transactions.id')
            ->limit($limit)
            ->get([
                'payment_transactions.id',
                'payment_transactions.amount',
                'payment_transactions.currency',
                'payment_transactions.provider',
                'payment_transactions.status',
                'payment_transactions.updated_at',
                'venues.name as venue_name',
                'orders.order_number',
            ]);

        return $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'venue_name' => (string) $row->venue_name,
            'order_number' => (string) $row->order_number,
            'amount' => number_format((float) $row->amount, 2, '.', ''),
            'currency' => (string) $row->currency,
            'provider' => (string) $row->provider,
            'status' => (string) $row->status,
            'verified_at' => $row->updated_at !== null
                ? (string) Carbon::parse((string) $row->updated_at)->toIso8601String()
                : null,
        ])->all();
    }
}
