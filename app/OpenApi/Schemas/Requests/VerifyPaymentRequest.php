<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Payments\VerifyPaymentRequest`. */
#[OA\Schema(
    schema: 'VerifyPaymentRequest',
    required: ['transaction_number'],
    properties: [
        new OA\Property(property: 'transaction_number', type: 'string', maxLength: 255, example: 'TX-1001'),
    ],
    type: 'object',
)]
final class VerifyPaymentRequest {}
