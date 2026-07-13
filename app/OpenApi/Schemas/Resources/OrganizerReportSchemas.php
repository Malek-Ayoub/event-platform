<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrganizerReportSales',
    properties: [
        new OA\Property(property: 'gross_sales', type: 'string', example: '1250.00'),
        new OA\Property(property: 'orders_count', type: 'integer', example: 42),
        new OA\Property(property: 'tickets_sold', type: 'integer', example: 80),
        new OA\Property(property: 'average_order_value', type: 'string', example: '29.76'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerReportRevenue',
    properties: [
        new OA\Property(property: 'gross_revenue', type: 'string', example: '1250.00'),
        new OA\Property(property: 'refunded_amount', type: 'string', example: '50.00'),
        new OA\Property(property: 'net_revenue', type: 'string', example: '1200.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerReportAttendance',
    properties: [
        new OA\Property(property: 'tickets_issued', type: 'integer', example: 80),
        new OA\Property(property: 'checked_in', type: 'integer', example: 60),
        new OA\Property(property: 'attendance_rate', type: 'string', example: '75.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerReportCommission',
    properties: [
        new OA\Property(property: 'commission_due', type: 'string', example: '125.00'),
        new OA\Property(property: 'commission_paid', type: 'string', example: '50.00'),
        new OA\Property(property: 'outstanding_commission', type: 'string', example: '75.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerReportMeta',
    properties: [
        new OA\Property(property: 'from', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'to', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'event_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
final class OrganizerReportSchemas {}
