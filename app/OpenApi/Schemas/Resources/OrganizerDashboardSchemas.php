<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrganizerDashboardKpis',
    properties: [
        new OA\Property(property: 'gross_sales', type: 'string', example: '12500.00'),
        new OA\Property(property: 'net_revenue', type: 'string', example: '12000.00'),
        new OA\Property(property: 'orders_count', type: 'integer', example: 84),
        new OA\Property(property: 'tickets_sold', type: 'integer', example: 160),
        new OA\Property(property: 'tickets_remaining', type: 'integer', example: 40),
        new OA\Property(property: 'attendance_rate', type: 'string', example: '72.50'),
        new OA\Property(property: 'outstanding_commission', type: 'string', example: '350.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerDashboardToday',
    properties: [
        new OA\Property(property: 'today_sales', type: 'string', example: '450.00'),
        new OA\Property(property: 'today_orders', type: 'integer', example: 6),
        new OA\Property(property: 'today_check_ins', type: 'integer', example: 18),
        new OA\Property(property: 'today_revenue', type: 'string', example: '430.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerDashboardEvent',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'tickets_sold', type: 'integer'),
        new OA\Property(property: 'capacity', type: 'integer'),
        new OA\Property(property: 'remaining', type: 'integer'),
        new OA\Property(property: 'status', type: 'string'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerDashboardOrder',
    properties: [
        new OA\Property(property: 'order_number', type: 'string'),
        new OA\Property(property: 'customer_name', type: 'string'),
        new OA\Property(property: 'amount', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerDashboardCheckIn',
    properties: [
        new OA\Property(property: 'ticket_number', type: 'string'),
        new OA\Property(property: 'holder_name', type: 'string'),
        new OA\Property(property: 'checked_in_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'gate', type: 'string', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerDashboardCommission',
    properties: [
        new OA\Property(property: 'due', type: 'string', example: '1250.00'),
        new OA\Property(property: 'paid', type: 'string', example: '900.00'),
        new OA\Property(property: 'outstanding', type: 'string', example: '350.00'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'OrganizerDashboardMeta',
    properties: [
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'generated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class OrganizerDashboardSchemas {}
