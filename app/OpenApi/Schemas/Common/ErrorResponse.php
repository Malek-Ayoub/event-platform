<?php

namespace App\OpenApi\Schemas\Common;

use OpenApi\Attributes as OA;

/** Generic API error envelope returned by `ApiExceptionRenderer`. */
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Resource not found.'),
    ],
    type: 'object',
)]
final class ErrorResponse {}
