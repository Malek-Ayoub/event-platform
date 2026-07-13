<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant ticket check-in routes. */
final class TenantTicketCheckInPaths
{
    #[OA\Post(
        path: '/api/tenant/tickets/check-in',
        operationId: 'tenant.tickets.check-in',
        summary: 'Check in a ticket by QR token',
        tags: ['Tickets'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['qr_token'],
                properties: [
                    new OA\Property(property: 'qr_token', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'gate_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'device_id', type: 'string', nullable: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ticket checked in',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/TicketCheckInResult'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 404, description: 'Ticket not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Ticket not eligible for check-in', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function store(): void {}
}
