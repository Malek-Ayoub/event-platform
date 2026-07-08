<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Orders\OrderResource`. */
#[OA\Schema(
    schema: 'OrderResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'event_id', type: 'integer', example: 1),
        new OA\Property(property: 'customer_user_id', type: 'integer', nullable: true),
        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-20260708-0001'),
        new OA\Property(property: 'subtotal', type: 'string', example: '150.00'),
        new OA\Property(property: 'tax_amount', type: 'string', example: '0.00'),
        new OA\Property(property: 'discount_amount', type: 'string', example: '0.00'),
        new OA\Property(property: 'total', type: 'string', example: '150.00'),
        new OA\Property(property: 'commission_amount', type: 'string', nullable: true),
        new OA\Property(property: 'coupon_id', type: 'integer', nullable: true),
        new OA\Property(property: 'promo_code_id', type: 'integer', nullable: true),
        new OA\Property(property: 'payment_method', type: 'string', nullable: true),
        new OA\Property(property: 'payment_reference', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'paid', 'failed', 'refunded', 'cancelled']),
        new OA\Property(property: 'customer_name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'customer_email', type: 'string', format: 'email'),
        new OA\Property(property: 'customer_phone', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class OrderResource {}
