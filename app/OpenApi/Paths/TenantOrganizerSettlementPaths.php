<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for organizer settlement read APIs (Phase 8.5.4). */
final class TenantOrganizerSettlementPaths
{
    #[OA\Get(
        path: '/api/tenant/organizer/settlement/summary',
        operationId: 'tenant.organizer.settlement.summary',
        summary: 'Organizer settlement summary',
        tags: ['Settlement'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settlement summary',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'summary', ref: '#/components/schemas/SettlementSummary'),
                                new OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: '#/components/schemas/SettlementLedgerEntry')),
                                new OA\Property(property: 'payments', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'meta', type: 'object'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function organizerSummary(): void {}

    #[OA\Get(
        path: '/api/tenant/organizer/settlement/entries',
        operationId: 'tenant.organizer.settlement.entries',
        summary: 'Organizer settlement ledger entries',
        tags: ['Settlement'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settlement ledger entries',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'summary', ref: '#/components/schemas/SettlementSummary'),
                                new OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: '#/components/schemas/SettlementLedgerEntry')),
                                new OA\Property(property: 'payments', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(property: 'meta', type: 'object'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function organizerEntries(): void {}
}
