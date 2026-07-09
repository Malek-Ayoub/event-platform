<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Payments\PaymentTransactionResource`. */
#[OA\Schema(
    schema: 'PaymentTransactionResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'order_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', example: 'apisyria'),
        new OA\Property(property: 'provider_transaction_id', type: 'string', example: 'APISYRIA-TX-1001', nullable: true),
        new OA\Property(property: 'transaction_number', type: 'string', example: 'TX-1001', nullable: true),
        new OA\Property(property: 'amount', type: 'string', example: '150.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', enum: [
            'pending',
            'completed',
            'failed',
            'refunded',
            'awaiting_transfer',
            'verifying',
            'paid',
            'expired',
        ]),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'payload', type: 'object', nullable: true, description: 'Visible only to users with orders.manage permission.'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class PaymentTransactionResource {}
