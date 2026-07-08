<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Events\TicketTypeResource`. */
#[OA\Schema(
    schema: 'TicketTypeResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'event_id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'price', type: 'string'),
        new OA\Property(property: 'quantity', type: 'integer'),
        new OA\Property(property: 'quantity_sold', type: 'integer'),
        new OA\Property(property: 'sale_start', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'sale_end', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'benefits', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'color', type: 'string', nullable: true),
        new OA\Property(property: 'version', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
    type: 'object',
)]
final class TicketTypeResource {}
