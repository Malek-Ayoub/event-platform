<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Payments\InitiatePaymentRequest`. */
#[OA\Schema(
    schema: 'InitiatePaymentRequest',
    required: ['order_id', 'provider'],
    properties: [
        new OA\Property(property: 'order_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', maxLength: 50, example: 'apisyria'),
    ],
    type: 'object',
)]
final class InitiatePaymentRequest {}
