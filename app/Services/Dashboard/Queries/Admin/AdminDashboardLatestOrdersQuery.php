<?php

namespace App\Services\Dashboard\Queries\Admin;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardLatestOrdersQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $limit = 5): array
    {
        $rows = DB::table('orders')
            ->join('venues', 'venues.id', '=', 'orders.venue_id')
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->limit($limit)
            ->get([
                'orders.order_number',
                'orders.customer_name',
                'orders.total',
                'orders.status',
                'orders.created_at',
                'venues.name as venue_name',
            ]);

        return $rows->map(fn (object $row): array => [
            'order_number' => (string) $row->order_number,
            'customer_name' => (string) $row->customer_name,
            'amount' => number_format((float) $row->total, 2, '.', ''),
            'status' => (string) $row->status,
            'venue_name' => (string) $row->venue_name,
            'created_at' => $row->created_at !== null
                ? (string) Carbon::parse((string) $row->created_at)->toIso8601String()
                : null,
        ])->all();
    }
}
