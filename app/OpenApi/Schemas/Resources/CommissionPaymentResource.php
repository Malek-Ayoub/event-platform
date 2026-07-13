<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CommissionPaymentResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'venue_id', type: 'integer'),
        new OA\Property(property: 'payment_account_id', type: 'integer', nullable: true),
        new OA\Property(property: 'amount', type: 'string', example: '20.00'),
        new OA\Property(property: 'currency', type: 'string', example: 'USD'),
        new OA\Property(
            property: 'payment_method',
            type: 'string',
            enum: ['cash', 'shamcash', 'syriatel_cash', 'bank_transfer', 'other'],
        ),
        new OA\Property(property: 'reference_number', type: 'string', nullable: true),
        new OA\Property(property: 'received_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'received_by_user_id', type: 'integer'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'metadata', type: 'object', nullable: true),
        new OA\Property(property: 'outstanding_commission', type: 'string', example: '10.00'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class CommissionPaymentResource {}
