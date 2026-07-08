<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Auth\RegisterRequest`. */
#[OA\Schema(
    schema: 'RegisterRequest',
    required: ['name', 'email', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Jane Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 50, nullable: true),
    ],
    type: 'object',
)]
final class RegisterRequest {}
