<?php

namespace App\OpenApi\Schemas\Common;

use OpenApi\Attributes as OA;

/** Validation error envelope returned by Laravel `ValidationException` handler. */
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
            example: ['email' => ['The email field is required.']],
        ),
    ],
    type: 'object',
)]
final class ValidationErrorResponse {}
