<?php

namespace App\OpenApi\Schemas\Requests;

use OpenApi\Attributes as OA;

/** Projection of `App\Http\Requests\Orders\CreateOrderRequest`. */
#[OA\Schema(
    schema: 'CreateOrderRequest',
    required: ['event_id', 'customer_name', 'customer_email', 'line_items'],
    properties: [
        new OA\Property(property: 'event_id', type: 'integer', example: 1),
        new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, example: 'Jane Doe'),
        new OA\Property(property: 'customer_email', type: 'string', format: 'email', example: 'jane@example.com'),
        new OA\Property(property: 'customer_phone', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'customer_user_id', type: 'integer', nullable: true),
        new OA\Property(property: 'reservation_id', type: 'integer', nullable: true),
        new OA\Property(
            property: 'line_items',
            type: 'array',
            minItems: 1,
            items: new OA\Items(
                required: ['ticket_type_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'ticket_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 2),
                ],
                type: 'object',
            ),
        ),
    ],
    type: 'object',
    example: [
        'event_id' => 1,
        'customer_name' => 'Jane Doe',
        'customer_email' => 'jane@example.com',
        'line_items' => [['ticket_type_id' => 1, 'quantity' => 2]],
    ],
)]
final class CreateOrderRequest {}
