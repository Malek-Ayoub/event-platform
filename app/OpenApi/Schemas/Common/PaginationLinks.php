<?php

namespace App\OpenApi\Schemas\Common;

use OpenApi\Attributes as OA;

/** Projection of paginated list `links` envelope (see `ApiResponse::paginationLinks`). */
#[OA\Schema(
    schema: 'PaginationLinks',
    properties: [
        new OA\Property(property: 'first', type: 'string', nullable: true),
        new OA\Property(property: 'last', type: 'string', nullable: true),
        new OA\Property(property: 'prev', type: 'string', nullable: true),
        new OA\Property(property: 'next', type: 'string', nullable: true),
    ],
    type: 'object',
)]
final class PaginationLinks {}
