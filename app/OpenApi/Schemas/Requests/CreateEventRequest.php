<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Events\CreateEventRequest`. */
#[OA\Schema(
    schema: 'CreateEventRequest',
    required: ['name', 'start_datetime', 'end_datetime'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Summer Festival'),
        new OA\Property(property: 'slug', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'category_id', type: 'integer', nullable: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'banner_url', type: 'string', maxLength: 2048, nullable: true),
        new OA\Property(property: 'gallery', type: 'array', items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'video_url', type: 'string', maxLength: 2048, nullable: true),
        new OA\Property(property: 'dj_info', type: 'object', nullable: true),
        new OA\Property(property: 'start_datetime', type: 'string', format: 'date-time', example: '2026-08-01T18:00:00+00:00'),
        new OA\Property(property: 'end_datetime', type: 'string', format: 'date-time', example: '2026-08-02T02:00:00+00:00'),
    ],
    type: 'object',
)]
final class CreateEventRequest {}
