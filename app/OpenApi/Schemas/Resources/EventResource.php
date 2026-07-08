<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Events\EventResource`. */
#[OA\Schema(
    schema: 'EventResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
        new OA\Property(property: 'name', type: 'string', example: 'Summer Festival'),
        new OA\Property(property: 'slug', type: 'string', example: 'summer-festival'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'banner_url', type: 'string', nullable: true),
        new OA\Property(property: 'gallery', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'video_url', type: 'string', nullable: true),
        new OA\Property(property: 'dj_info', type: 'object', nullable: true),
        new OA\Property(property: 'start_datetime', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_datetime', type: 'string', format: 'date-time'),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'cancelled', 'completed']),
        new OA\Property(property: 'version', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class EventResource {}
