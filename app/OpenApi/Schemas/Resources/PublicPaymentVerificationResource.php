<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Payments\PublicPaymentVerificationResource`. */
#[OA\Schema(
    schema: 'PublicPaymentVerificationResource',
    description: 'Guest-facing verification outcome. Internal payment fields are omitted.',
    required: ['status', 'message'],
    properties: [
        new OA\Property(
            property: 'status',
            type: 'string',
            enum: ['paid', 'failed', 'verifying', 'awaiting_transfer', 'expired'],
            example: 'paid',
        ),
        new OA\Property(property: 'message', type: 'string', example: 'Payment confirmed.'),
    ],
    type: 'object',
)]
final class PublicPaymentVerificationResource {}
