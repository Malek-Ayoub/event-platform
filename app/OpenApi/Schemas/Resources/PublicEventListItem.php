<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Events\PublicEventListItemResource`. */
#[OA\Schema(
    schema: 'PublicEventListItem',
    description: 'Public catalog projection of a published event for anonymous visitors. Intentionally separate from tenant EventResource.',
    required: ['id', 'slug', 'title', 'description', 'venue', 'starts_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'slug', type: 'string', example: 'summer-jazz-night'),
        new OA\Property(property: 'title', type: 'string', example: 'Summer Jazz Night'),
        new OA\Property(property: 'description', type: 'string', example: 'A short teaser description for the catalog listing.'),
        new OA\Property(property: 'venue', type: 'string', example: 'Harborview Pavilion'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true),
        new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2026-08-15T19:30:00Z'),
        new OA\Property(
            property: 'starting_price',
            properties: [
                new OA\Property(
                    property: 'amount',
                    type: 'string',
                    example: '45.00',
                    description: 'Decimal amount as string (consistent with other money schemas in this spec).',
                ),
                new OA\Property(property: 'currency', type: 'string', minLength: 3, maxLength: 3, example: 'USD'),
            ],
            type: 'object',
            nullable: true,
        ),
    ],
    type: 'object',
)]
final class PublicEventListItem {}
