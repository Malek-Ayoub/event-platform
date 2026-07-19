<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'VenueResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'slug', type: 'string'),
        new OA\Property(property: 'subdomain', type: 'string'),
        new OA\Property(property: 'status', type: 'string', enum: ['active', 'suspended', 'pending']),
        new OA\Property(property: 'commission_rate', type: 'string', example: '1.00'),
        new OA\Property(
            property: 'owner',
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ],
            type: 'object',
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object',
)]
final class VenueResource {}
