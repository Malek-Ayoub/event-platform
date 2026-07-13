<?php

namespace App\OpenApi\Paths;

use App\OpenApi\Schemas\Examples\AdminDashboardResponseExamples;
use OpenApi\Attributes as OA;

/** OpenAPI path projections for admin dashboard read APIs (Phase 8.7). */
final class AdminDashboardPaths
{
    #[OA\Get(
        path: '/api/admin/dashboard',
        operationId: 'admin.dashboard.show',
        summary: 'Admin dashboard overview',
        description: 'Read-only composition endpoint that aggregates platform KPIs, today metrics, top venues/events, latest activity, and critical alerts for the platform admin home screen.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Admin dashboard overview',
                content: new OA\JsonContent(
                    example: AdminDashboardResponseExamples::DASHBOARD,
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'kpis', ref: '#/components/schemas/AdminDashboardKpis'),
                                new OA\Property(property: 'today', ref: '#/components/schemas/AdminDashboardToday'),
                                new OA\Property(property: 'top_venues', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminReportTopVenue')),
                                new OA\Property(property: 'top_events', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminReportTopEvent')),
                                new OA\Property(property: 'latest_orders', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminDashboardOrder')),
                                new OA\Property(property: 'latest_payments', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminDashboardPayment')),
                                new OA\Property(property: 'latest_check_ins', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminDashboardCheckIn')),
                                new OA\Property(property: 'alerts', type: 'array', items: new OA\Items(ref: '#/components/schemas/AdminDashboardAlert')),
                                new OA\Property(property: 'meta', ref: '#/components/schemas/AdminDashboardMeta'),
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
    public function show(): void {}
}
