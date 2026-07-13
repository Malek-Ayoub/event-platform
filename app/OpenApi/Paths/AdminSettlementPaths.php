<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for admin settlement read APIs (Phase 8.5.4). */
final class AdminSettlementPaths
{
    #[OA\Get(
        path: '/api/admin/settlement/venues',
        operationId: 'admin.settlement.venues.index',
        summary: 'List venue settlement summaries',
        tags: ['Admin', 'Settlement'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['name', 'gross_sales', 'commission_paid', 'outstanding', 'outstanding_commission', 'last_payment'])),
            new OA\Parameter(name: 'direction', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'min_outstanding', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Venue settlement list', content: new OA\JsonContent(ref: '#/components/schemas/AdminSettlementVenueListResponse')),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function venueList(): void {}

    #[OA\Get(
        path: '/api/admin/venues/{venue}/settlement',
        operationId: 'admin.venues.settlement.show',
        summary: 'Venue settlement statement',
        tags: ['Admin', 'Settlement'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'venue', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Venue settlement statement',
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
            new OA\Response(response: 404, description: 'Venue not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function venueStatement(): void {}
}
