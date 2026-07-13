<?php

namespace App\OpenApi\Schemas\Resources;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TicketCheckInResult',
    required: ['valid', 'ticket_number', 'holder_name', 'event_name', 'status'],
    properties: [
        new OA\Property(property: 'valid', type: 'boolean', example: true),
        new OA\Property(property: 'ticket_number', type: 'string', example: 'EV000001-260801-000001'),
        new OA\Property(property: 'holder_name', type: 'string', example: 'Jane Doe'),
        new OA\Property(property: 'event_name', type: 'string', example: 'Summer Fest'),
        new OA\Property(property: 'status', type: 'string', enum: ['checked_in']),
    ],
    type: 'object',
)]
final class TicketCheckInResultSchema {}
