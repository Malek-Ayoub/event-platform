<?php

namespace App\OpenApi\Schemas\Examples;

/** Full response example for organizer dashboard API (Phase 8.6). */
final class OrganizerDashboardResponseExamples
{
    /** @var array<string, mixed> */
    public const DASHBOARD = [
        'data' => [
            'kpis' => [
                'gross_sales' => '12500.00',
                'net_revenue' => '12000.00',
                'orders_count' => 84,
                'tickets_sold' => 160,
                'tickets_remaining' => 40,
                'attendance_rate' => '72.50',
                'outstanding_commission' => '350.00',
            ],
            'today' => [
                'today_sales' => '450.00',
                'today_orders' => 6,
                'today_check_ins' => 18,
                'today_revenue' => '430.00',
            ],
            'events' => [
                [
                    'id' => 12,
                    'name' => 'Summer Festival',
                    'starts_at' => '2026-08-15T18:00:00+00:00',
                    'tickets_sold' => 120,
                    'capacity' => 200,
                    'remaining' => 80,
                    'status' => 'published',
                ],
            ],
            'latest_orders' => [
                [
                    'order_number' => 'ORD-10042',
                    'customer_name' => 'Jane Doe',
                    'amount' => '75.00',
                    'status' => 'paid',
                    'created_at' => '2026-07-13T14:22:00+00:00',
                ],
            ],
            'latest_check_ins' => [
                [
                    'ticket_number' => 'EVT-001-000123',
                    'holder_name' => 'Jane Doe',
                    'checked_in_at' => '2026-07-13T15:05:00+00:00',
                    'gate' => 'Gate 1',
                ],
            ],
            'commission' => [
                'due' => '1250.00',
                'paid' => '900.00',
                'outstanding' => '350.00',
            ],
            'meta' => [
                'currency' => 'USD',
                'generated_at' => '2026-07-13T16:00:00+00:00',
            ],
        ],
    ];
}
