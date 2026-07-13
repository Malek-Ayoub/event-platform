<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SettlementSummary',
    properties: [
        new OA\Property(property: 'gross_sales', type: 'string', example: '125000.00'),
        new OA\Property(property: 'tickets_sold', type: 'integer', example: 320),
        new OA\Property(property: 'commission_due', type: 'string', example: '1250.00'),
        new OA\Property(property: 'commission_paid', type: 'string', example: '500.00'),
        new OA\Property(property: 'commission_adjustments', type: 'string', example: '50.00'),
        new OA\Property(property: 'commission_outstanding', type: 'string', example: '700.00'),
        new OA\Property(property: 'refunds', type: 'string', example: '5000.00'),
        new OA\Property(property: 'net_sales', type: 'string', example: '120000.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'SettlementLedgerEntry',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'date', type: 'string', format: 'date-time'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'credit', type: 'string', example: '10.00'),
        new OA\Property(property: 'debit', type: 'string', example: '0.00'),
        new OA\Property(property: 'balance', type: 'string', example: '150.00'),
        new OA\Property(property: 'order_id', type: 'integer', nullable: true),
        new OA\Property(property: 'event_id', type: 'integer', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'AdminSettlementVenueListResponse',
    properties: [
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        new OA\Property(property: 'meta', type: 'object'),
    ],
    type: 'object',
)]
final class SettlementStatementSchemas {}
