<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CreateVenueRequest',
    required: ['name', 'subdomain', 'owner_name', 'owner_email', 'owner_password'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Harbor Hall'),
        new OA\Property(
            property: 'subdomain',
            type: 'string',
            maxLength: 255,
            pattern: '^[a-z0-9-]+$',
            example: 'harbor-hall',
        ),
        new OA\Property(property: 'owner_name', type: 'string', maxLength: 255, example: 'Sam Organizer'),
        new OA\Property(property: 'owner_email', type: 'string', format: 'email', example: 'owner@harbor.test'),
        new OA\Property(property: 'owner_password', type: 'string', format: 'password', minLength: 8),
    ],
    type: 'object',
)]
final class CreateVenueRequest {}
