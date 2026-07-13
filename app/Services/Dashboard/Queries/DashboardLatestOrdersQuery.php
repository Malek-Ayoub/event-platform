<?php

namespace App\Services\Dashboard\Queries;

use App\Models\Order;
use App\Models\Scopes\BelongsToVenueScope;

class DashboardLatestOrdersQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(int $venueId, int $limit = 5): array
    {
        return Order::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'order_number',
                'customer_name',
                'total',
                'status',
                'created_at',
            ])
            ->map(fn (Order $order): array => [
                'order_number' => (string) $order->order_number,
                'customer_name' => (string) $order->customer_name,
                'amount' => number_format((float) $order->total, 2, '.', ''),
                'status' => $order->status->value,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
