<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'UpdateVenueRequest',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Harbor Hall'),
        new OA\Property(property: 'commission_rate', type: 'number', minimum: 0, maximum: 100, example: 2.5),
    ],
    type: 'object',
)]
final class UpdateVenueRequest {}
