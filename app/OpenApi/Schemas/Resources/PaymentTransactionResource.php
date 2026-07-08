<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Payments\PaymentTransactionResource`. */
#[OA\Schema(
    schema: 'PaymentTransactionResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'order_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', example: 'shamcash'),
        new OA\Property(property: 'provider_transaction_id', type: 'string', example: 'TXN-12345'),
        new OA\Property(property: 'amount', type: 'string', example: '150.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'completed', 'failed', 'refunded']),
        new OA\Property(property: 'payload', type: 'object', nullable: true, description: 'Visible only to users with orders.manage permission.'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class PaymentTransactionResource {}
