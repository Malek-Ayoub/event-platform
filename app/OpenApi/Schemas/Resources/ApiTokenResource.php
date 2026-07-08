<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Resources\Auth\ApiTokenResource`. */
#[OA\Schema(
    schema: 'ApiTokenResource',
    properties: [
        new OA\Property(property: 'user', ref: '#/components/schemas/AuthenticatedUserResource'),
        new OA\Property(property: 'token', type: 'string', example: '1|plainTextToken'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
    ],
    type: 'object',
)]
final class ApiTokenResource {}
