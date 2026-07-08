<?php

namespace App\OpenApi\Schemas\Common;

use OpenApi\Attributes as OA;

/** Plain message envelope returned by `ApiResponse::plainMessage()`. */
#[OA\Schema(
    schema: 'MessageResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Operation completed successfully.'),
    ],
    type: 'object',
)]
final class MessageResponse {}
