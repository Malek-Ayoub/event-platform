<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AdminReportPlatform',
    properties: [
        new OA\Property(property: 'gross_revenue', type: 'string', example: '50000.00'),
        new OA\Property(property: 'net_revenue', type: 'string', example: '47500.00'),
        new OA\Property(property: 'orders_count', type: 'integer', example: 320),
        new OA\Property(property: 'tickets_sold', type: 'integer', example: 640),
        new OA\Property(property: 'active_venues', type: 'integer', example: 12),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminReportCommissions',
    properties: [
        new OA\Property(property: 'commission_due', type: 'string', example: '5000.00'),
        new OA\Property(property: 'commission_paid', type: 'string', example: '2500.00'),
        new OA\Property(property: 'commission_adjustments', type: 'string', example: '150.00'),
        new OA\Property(property: 'outstanding_commission', type: 'string', example: '2350.00'),
        new OA\Property(property: 'monthly', type: 'array', items: new OA\Items(
            properties: [
                new OA\Property(property: 'month', type: 'string', example: '2026-01'),
                new OA\Property(property: 'commission_due', type: 'string', example: '1200.00'),
                new OA\Property(property: 'commission_paid', type: 'string', example: '800.00'),
            ],
            type: 'object',
        )),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminReportTopVenue',
    properties: [
        new OA\Property(property: 'venue_id', type: 'integer'),
        new OA\Property(property: 'venue_name', type: 'string'),
        new OA\Property(property: 'subdomain', type: 'string'),
        new OA\Property(property: 'gross_sales', type: 'string'),
        new OA\Property(property: 'commission_due', type: 'string'),
        new OA\Property(property: 'outstanding_commission', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminReportTopEvent',
    properties: [
        new OA\Property(property: 'event_id', type: 'integer'),
        new OA\Property(property: 'event_name', type: 'string'),
        new OA\Property(property: 'venue_id', type: 'integer'),
        new OA\Property(property: 'venue_name', type: 'string'),
        new OA\Property(property: 'gross_sales', type: 'string'),
        new OA\Property(property: 'tickets_sold', type: 'integer'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminReportPaymentMethod',
    properties: [
        new OA\Property(property: 'method', type: 'string', example: 'shamcash'),
        new OA\Property(property: 'transactions_count', type: 'integer', example: 42),
        new OA\Property(property: 'total_amount', type: 'string', example: '12500.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminReportRefunds',
    properties: [
        new OA\Property(property: 'refunds_count', type: 'integer', example: 8),
        new OA\Property(property: 'refunded_amount', type: 'string', example: '2500.00'),
        new OA\Property(property: 'refund_rate', type: 'string', example: '5.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminReportMeta',
    properties: [
        new OA\Property(property: 'from', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'to', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'limit', type: 'integer', example: 10),
    ],
    type: 'object',
)]
final class AdminReportSchemas {}
