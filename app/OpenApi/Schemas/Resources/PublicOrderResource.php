<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Orders\PublicOrderResource`. */
#[OA\Schema(
    schema: 'PublicOrderResource',
    description: 'Limited public projection of a guest-created order. Internal financial fields are omitted.',
    required: ['id', 'order_number', 'status', 'total', 'customer_name', 'customer_email'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'order_number', type: 'string', example: 'ORD-ABC12345'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'total', type: 'string', example: '150.00'),
        new OA\Property(property: 'customer_name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'customer_email', type: 'string', format: 'email', example: 'jane@example.com'),
    ],
    type: 'object',
)]
final class PublicOrderResource {}
