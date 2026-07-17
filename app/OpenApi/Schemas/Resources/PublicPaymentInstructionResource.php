<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Payments\PublicPaymentInstructionResource`. */
#[OA\Schema(
    schema: 'PublicPaymentInstructionResource',
    description: 'Guest-facing payment instructions. Numeric payment and account ids are intentionally omitted.',
    required: ['provider', 'merchant_account', 'amount', 'currency', 'expires_at', 'instructions'],
    properties: [
        new OA\Property(property: 'provider', type: 'string', example: 'shamcash', description: 'Wallet brand label for the guest.'),
        new OA\Property(property: 'merchant_account', type: 'string', example: 'WALLET-001'),
        new OA\Property(property: 'amount', type: 'string', example: '120.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'instructions', type: 'string'),
    ],
    type: 'object',
)]
final class PublicPaymentInstructionResource {}
