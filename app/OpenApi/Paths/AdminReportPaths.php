<?php

namespace App\OpenApi\Paths;

use App\OpenApi\Schemas\Examples\ReportResponseExamples;
use OpenApi\Attributes as OA;

/** OpenAPI path projections for admin report read APIs (Phase 8.5.5). */
final class AdminReportPaths
{
    #[OA\Get(
        path: '/api/admin/reports',
        operationId: 'admin.reports.show',
        summary: 'Platform operational and financial report',
        description: 'Read-only platform-wide report for super administrators. Aggregates revenue, commissions, top venues/events, payment methods, and refunds.',
        tags: ['Admin', 'Reports'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'from',
                in: 'query',
                required: false,
                description: 'Inclusive start date (applied as start of day).',
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
                name: 'limit',
                in: 'query',
                required: false,
                description: 'Maximum rows returned for `top_venues` and `top_events` (default 10, max 100).',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 10, example: 10),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin platform report',
                content: new OA\JsonContent(
                    example: ReportResponseExamples::ADMIN_REPORT,
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'platform', ref: '#/components/schemas/AdminReportPlatform'),
                                new OA\Property(property: 'commissions', ref: '#/components/schemas/AdminReportCommissions'),
                                new OA\Property(property: 'top_venues', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminReportTopVenue')),
                                new OA\Property(property: 'top_events', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminReportTopEvent')),
                                new OA\Property(property: 'payment_methods', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminReportPaymentMethod')),
                                new OA\Property(property: 'refunds', ref: '#/components/schemas/AdminReportRefunds'),
                                new OA\Property(property: 'meta', ref: '#/components/schemas/AdminReportMeta'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function show(): void {}
}
