<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Payments\PaymentInstructionResource`. */
#[OA\Schema(
    schema: 'PaymentInstructionResource',
    properties: [
        new OA\Property(property: 'payment_id', type: 'integer', example: 1),
        new OA\Property(property: 'provider', type: 'string', example: 'apisyria'),
        new OA\Property(property: 'merchant_account', type: 'string', example: 'WALLET-001'),
        new OA\Property(property: 'amount', type: 'string', example: '120.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'instructions', type: 'string'),
    ],
    type: 'object',
)]
final class PaymentInstructionResource {}
