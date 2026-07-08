<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Commerce\CouponResource`. */
#[OA\Schema(
    schema: 'CouponResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'code', type: 'string'),
        new OA\Property(property: 'discount_type', type: 'string', enum: ['percent', 'fixed']),
        new OA\Property(property: 'discount_value', type: 'string'),
        new OA\Property(property: 'min_order_amount', type: 'string', nullable: true),
        new OA\Property(property: 'max_uses', type: 'integer', nullable: true),
        new OA\Property(property: 'used_count', type: 'integer'),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class CouponResource {}
