<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Auth\LoginRequest`. */
#[OA\Schema(
    schema: 'LoginRequest',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'owner@venue.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
        new OA\Property(property: 'device_name', type: 'string', maxLength: 255, nullable: true, example: 'web'),
    ],
    type: 'object',
)]
final class LoginRequest {}
