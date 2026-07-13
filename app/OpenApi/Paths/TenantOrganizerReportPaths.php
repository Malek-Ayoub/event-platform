<?php

namespace App\OpenApi\Paths;

use App\OpenApi\Schemas\Examples\ReportResponseExamples;
use OpenApi\Attributes as OA;

/** OpenAPI path projections for organizer report read APIs (Phase 8.5.5). */
final class TenantOrganizerReportPaths
{
    #[OA\Get(
        path: '/api/tenant/organizer/reports',
        operationId: 'tenant.organizer.reports.show',
        summary: 'Organizer operational and financial report',
        description: 'Read-only venue report aggregating sales, revenue, attendance, and commission metrics. Supports optional date range and event filters.',
        tags: ['Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'from',
                in: 'query',
                required: false,
                description: 'Inclusive start date (venue timezone applied as start of day).',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-01'),
            ),
            new OA\Parameter(
                name: 'to',
                in: 'query',
                required: false,
                description: 'Inclusive end date (applied as end of day). Must be on or after `from`.',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-01-31'),
            ),
            new OA\Parameter(
                name: 'event_id',
                in: 'query',
                required: false,
                description: 'Optional event scope within the current venue.',
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 12),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organizer report',
                content: new OA\JsonContent(
                    example: ReportResponseExamples::ORGANIZER_REPORT,
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
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function show(): void {}
}
