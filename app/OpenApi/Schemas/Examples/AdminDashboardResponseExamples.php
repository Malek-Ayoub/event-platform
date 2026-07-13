<?php

namespace App\OpenApi\Schemas\Examples;

/** Full response example for admin dashboard API (Phase 8.7). */
final class AdminDashboardResponseExamples
{
    /** @var array<string, mixed> */
    public const DASHBOARD = [
        'data' => [
            'kpis' => [
                'gross_revenue' => '125000.00',
                'net_revenue' => '120000.00',
                'commission_due' => '12500.00',
                'commission_paid' => '9000.00',
                'outstanding_commission' => '3500.00',
                'active_events' => 42,
                'active_venues' => 18,
                'orders_count' => 2400,
                'tickets_sold' => 4800,
            ],
            'today' => [
                'today_sales' => '4500.00',
                'today_revenue' => '4300.00',
                'today_orders' => 60,
                'today_check_ins' => 180,
                'events_starting_today' => 3,
            ],
            'top_venues' => [
                [
                    'venue_id' => 1,
                    'venue_name' => 'Alpha Venue',
                    'subdomain' => 'alpha',
                    'gross_sales' => '50000.00',
                    'commission_due' => '5000.00',
                    'outstanding_commission' => '1200.00',
                ],
            ],
            'top_events' => [
                [
                    'event_id' => 12,
                    'event_name' => 'Summer Festival',
                    'venue_name' => 'Alpha Venue',
                    'gross_sales' => '25000.00',
                    'tickets_sold' => 500,
                ],
            ],
            'latest_orders' => [
                [
                    'order_number' => 'ORD-10042',
                    'customer_name' => 'Jane Doe',
                    'amount' => '75.00',
                    'status' => 'paid',
                    'venue_name' => 'Alpha Venue',
                    'created_at' => '2026-07-13T14:22:00+00:00',
                ],
            ],
            'latest_payments' => [
                [
                    'id' => 501,
                    'venue_name' => 'Alpha Venue',
                    'order_number' => 'ORD-10042',
                    'amount' => '75.00',
                    'currency' => 'USD',
                    'provider' => 'shamcash',
                    'status' => 'paid',
                    'verified_at' => '2026-07-13T14:25:00+00:00',
                ],
            ],
            'latest_check_ins' => [
                [
                    'ticket_number' => 'EVT-001-000123',
                    'holder_name' => 'Jane Doe',
                    'venue_name' => 'Alpha Venue',
                    'checked_in_at' => '2026-07-13T15:05:00+00:00',
                    'gate' => 'Gate 1',
                ],
            ],
            'alerts' => [
                [
                    'type' => 'outstanding_commission',
                    'severity' => 'warning',
                    'count' => 4,
                    'amount' => '3500.00',
                    'message' => 'Organizers owe platform commission.',
                ],
                [
                    'type' => 'events_starting_today',
                    'severity' => 'info',
                    'count' => 3,
                    'message' => 'Events scheduled to start today.',
                ],
                [
                    'type' => 'failed_payment_verifications',
                    'severity' => 'danger',
                    'count' => 2,
                    'message' => 'Failed payment verifications in the last 24 hours.',
                ],
            ],
            'meta' => [
                'currency' => 'USD',
                'generated_at' => '2026-07-13T16:00:00+00:00',
            ],
        ],
    ];
}
