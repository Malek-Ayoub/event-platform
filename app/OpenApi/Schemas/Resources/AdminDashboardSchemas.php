<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AdminDashboardKpis',
    properties: [
        new OA\Property(property: 'gross_revenue', type: 'string', example: '125000.00'),
        new OA\Property(property: 'net_revenue', type: 'string', example: '120000.00'),
        new OA\Property(property: 'commission_due', type: 'string', example: '12500.00'),
        new OA\Property(property: 'commission_paid', type: 'string', example: '9000.00'),
        new OA\Property(property: 'outstanding_commission', type: 'string', example: '3500.00'),
        new OA\Property(property: 'active_events', type: 'integer', example: 42),
        new OA\Property(property: 'active_venues', type: 'integer', example: 18),
        new OA\Property(property: 'orders_count', type: 'integer', example: 2400),
        new OA\Property(property: 'tickets_sold', type: 'integer', example: 4800),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminDashboardToday',
    properties: [
        new OA\Property(property: 'today_sales', type: 'string', example: '4500.00'),
        new OA\Property(property: 'today_revenue', type: 'string', example: '4300.00'),
        new OA\Property(property: 'today_orders', type: 'integer', example: 60),
        new OA\Property(property: 'today_check_ins', type: 'integer', example: 180),
        new OA\Property(property: 'events_starting_today', type: 'integer', example: 3),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminDashboardOrder',
    properties: [
        new OA\Property(property: 'order_number', type: 'string'),
        new OA\Property(property: 'customer_name', type: 'string'),
        new OA\Property(property: 'amount', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'venue_name', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminDashboardPayment',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'venue_name', type: 'string'),
        new OA\Property(property: 'order_number', type: 'string'),
        new OA\Property(property: 'amount', type: 'string'),
        new OA\Property(property: 'currency', type: 'string'),
        new OA\Property(property: 'provider', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'verified_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminDashboardCheckIn',
    properties: [
        new OA\Property(property: 'ticket_number', type: 'string'),
        new OA\Property(property: 'holder_name', type: 'string'),
        new OA\Property(property: 'venue_name', type: 'string'),
        new OA\Property(property: 'checked_in_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'gate', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminDashboardAlert',
    properties: [
        new OA\Property(property: 'type', type: 'string', enum: ['outstanding_commission', 'events_starting_today', 'failed_payment_verifications']),
        new OA\Property(property: 'severity', type: 'string', enum: ['info', 'warning', 'danger']),
        new OA\Property(property: 'count', type: 'integer'),
        new OA\Property(property: 'amount', type: 'string', nullable: true),
        new OA\Property(property: 'message', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminDashboardMeta',
    properties: [
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class AdminDashboardSchemas {}
