<?php

namespace App\OpenApi\Schemas\Examples;

/** Full response examples for report APIs (Phase 8.5.5.3). */
final class ReportResponseExamples
{
    /** @var array<string, mixed> */
    public const ORGANIZER_REPORT = [
        'data' => [
            'sales' => [
                'gross_sales' => '1250.00',
                'orders_count' => 42,
                'tickets_sold' => 80,
                'average_order_value' => '29.76',
            ],
            'revenue' => [
                'gross_revenue' => '1250.00',
                'refunded_amount' => '50.00',
                'net_revenue' => '1200.00',
            ],
            'attendance' => [
                'tickets_issued' => 80,
                'checked_in' => 60,
                'attendance_rate' => '75.00',
            ],
            'commission' => [
                'commission_due' => '125.00',
                'commission_paid' => '50.00',
                'outstanding_commission' => '75.00',
            ],
            'meta' => [
                'from' => '2026-01-01T00:00:00+00:00',
                'to' => '2026-01-31T23:59:59+00:00',
                'currency' => 'USD',
                'event_id' => null,
            ],
        ],
    ];

    /** @var array<string, mixed> */
    public const ADMIN_REPORT = [
        'data' => [
            'platform' => [
                'gross_revenue' => '50000.00',
                'net_revenue' => '47500.00',
                'orders_count' => 320,
                'tickets_sold' => 640,
                'active_venues' => 12,
            ],
            'commissions' => [
                'commission_due' => '5000.00',
                'commission_paid' => '2500.00',
                'commission_adjustments' => '150.00',
                'outstanding_commission' => '2350.00',
                'monthly' => [
                    [
                        'month' => '2026-01',
                        'commission_due' => '1200.00',
                        'commission_paid' => '800.00',
                    ],
                ],
            ],
            'top_venues' => [
                [
                    'venue_id' => 1,
                    'venue_name' => 'Alpha Venue',
                    'subdomain' => 'alpha',
                    'gross_sales' => '20000.00',
                    'commission_due' => '2000.00',
                    'outstanding_commission' => '500.00',
                ],
            ],
            'top_events' => [
                [
                    'event_id' => 10,
                    'event_name' => 'Summer Festival',
                    'venue_id' => 1,
                    'venue_name' => 'Alpha Venue',
                    'gross_sales' => '8000.00',
                    'tickets_sold' => 400,
                ],
            ],
            'payment_methods' => [
                [
                    'method' => 'shamcash',
                    'transactions_count' => 180,
                    'total_amount' => '30000.00',
                ],
            ],
            'refunds' => [
                'refunds_count' => 8,
                'refunded_amount' => '2500.00',
                'refund_rate' => '5.00',
            ],
            'meta' => [
                'from' => '2026-01-01T00:00:00+00:00',
                'to' => '2026-01-31T23:59:59+00:00',
                'currency' => 'USD',
                'limit' => 10,
            ],
        ],
    ];
}
