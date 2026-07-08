<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Payments\InitiatePaymentRequest`. */
#[OA\Schema(
    schema: 'InitiatePaymentRequest',
    required: ['order_id', 'provider'],
    properties: [
        new OA\Property(property: 'order_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', maxLength: 50, example: 'shamcash'),
        new OA\Property(property: 'amount', type: 'number', minimum: 0, example: 150.00, nullable: true),
        new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD', nullable: true),
        new OA\Property(property: 'metadata', type: 'object', nullable: true),
    ],
    type: 'object',
)]
final class InitiatePaymentRequest {}
