<?php

namespace App\OpenApi\Schemas\Common;

use OpenApi\Attributes as OA;

/** Projection of paginated list `meta` envelope (see `ApiResponse::paginationMeta`). */
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'from', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'last_page', type: 'integer', example: 3),
        new OA\Property(property: 'path', type: 'string', example: '/api/tenant/events'),
        new OA\Property(property: 'per_page', type: 'integer', example: 15),
        new OA\Property(property: 'to', type: 'integer', nullable: true, example: 15),
        new OA\Property(property: 'total', type: 'integer', example: 42),
    ],
    type: 'object',
)]
final class PaginationMeta {}
