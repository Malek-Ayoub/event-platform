<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for organizer report read APIs (Phase 8.5.5.1). */
final class TenantOrganizerReportPaths
{
    #[OA\Get(
        path: '/api/tenant/organizer/reports',
        operationId: 'tenant.organizer.reports.show',
        summary: 'Organizer operational and financial report',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'event_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organizer report',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'sales', ref: '#/components/schemas/OrganizerReportSales'),
                                new OA\Property(property: 'revenue', ref: '#/components/schemas/OrganizerReportRevenue'),
                                new OA\Property(property: 'attendance', ref: '#/components/schemas/OrganizerReportAttendance'),
                                new OA\Property(property: 'commission', ref: '#/components/schemas/OrganizerReportCommission'),
                                new OA\Property(property: 'meta', ref: '#/components/schemas/OrganizerReportMeta'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 404, description: 'Event not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(): void {}
}
