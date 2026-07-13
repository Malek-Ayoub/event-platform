<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RecordCommissionPaymentRequest',
    required: ['venue_id', 'amount', 'payment_method', 'received_at'],
    properties: [
        new OA\Property(property: 'venue_id', type: 'integer', minimum: 1),
        new OA\Property(property: 'amount', type: 'string', example: '20.00'),
        new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
        new OA\Property(
            property: 'payment_method',
            type: 'string',
            enum: ['cash', 'shamcash', 'syriatel_cash', 'bank_transfer', 'other'],
        ),
        new OA\Property(property: 'reference_number', type: 'string', nullable: true),
        new OA\Property(property: 'received_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'payment_account_id', type: 'integer', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class RecordCommissionPaymentRequest {}
