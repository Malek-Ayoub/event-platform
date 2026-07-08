<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Payments\InitiatePaymentRequest`. */
#[OA\Schema(
    schema: 'InitiatePaymentRequest',
    required: ['order_id', 'provider', 'provider_transaction_id', 'amount'],
    properties: [
        new OA\Property(property: 'order_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', maxLength: 50, example: 'shamcash'),
        new OA\Property(property: 'provider_transaction_id', type: 'string', maxLength: 255, example: 'TXN-12345'),
        new OA\Property(property: 'amount', type: 'number', minimum: 0, example: 150.00),
        new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
        new OA\Property(property: 'payload', type: 'object', nullable: true),
    ],
    type: 'object',
)]
final class InitiatePaymentRequest {}
