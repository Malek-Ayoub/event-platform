<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Events\PublicEventDetailResource`. */
#[OA\Schema(
    schema: 'PublicEventDetailItem',
    description: 'Public catalog detail projection of a published event for anonymous visitors.',
    required: ['id', 'slug', 'title', 'description', 'venue', 'starts_at', 'ticket_types'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', example: 'summer-jazz-night'),
        new OA\Property(property: 'title', type: 'string', example: 'Summer Jazz Night'),
        new OA\Property(property: 'description', type: 'string', example: 'A short teaser description for the catalog listing.'),
        new OA\Property(property: 'venue', type: 'string', example: 'Harborview Pavilion'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-08-15T19:30:00Z'),
        new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', nullable: true, example: '2026-08-15T22:30:00Z'),
        new OA\Property(
            property: 'starting_price',
            properties: [
                new OA\Property(property: 'amount', type: 'string', example: '45.00'),
                new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'ticket_types',
            type: 'array',
            items: new OA\Items(
                required: ['id', 'name', 'price', 'remaining', 'is_available'],
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'General Admission'),
                    new OA\Property(
                        property: 'price',
                        required: ['amount', 'currency'],
                        properties: [
                            new OA\Property(property: 'amount', type: 'string', example: '45.00'),
                            new OA\Property(property: 'currency', type: 'string', example: 'USD'),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(property: 'remaining', type: 'integer', example: 42),
                    new OA\Property(property: 'is_available', type: 'boolean', example: true),
                    new OA\Property(property: 'benefits', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
                    new OA\Property(property: 'color', type: 'string', nullable: true, example: '#336699'),
                ],
                type: 'object',
            ),
        ),
    ],
    type: 'object',
)]
final class PublicEventDetailItem {}
