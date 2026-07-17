<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Payments\SubmitPublicPaymentVerificationRequest` body. */
#[OA\Schema(
    schema: 'SubmitPublicPaymentVerificationRequest',
    required: ['transaction_number'],
    properties: [
        new OA\Property(
            property: 'transaction_number',
            type: 'string',
            maxLength: 255,
            example: 'TX-1001',
            description: 'Wallet provider transfer reference submitted by the guest.',
        ),
    ],
    type: 'object',
)]
final class SubmitPublicPaymentVerificationRequest {}
