<?php

namespace App\OpenApi\Paths;

use App\OpenApi\Schemas\Examples\OrganizerDashboardResponseExamples;
use OpenApi\Attributes as OA;

/** OpenAPI path projections for organizer dashboard read APIs (Phase 8.6). */
final class TenantOrganizerDashboardPaths
{
    #[OA\Get(
        path: '/api/tenant/organizer/dashboard',
        operationId: 'tenant.organizer.dashboard.show',
        summary: 'Organizer dashboard overview',
        description: 'Read-only composition endpoint that aggregates KPIs, today metrics, upcoming events, latest orders, latest check-ins, and commission status for the organizer home screen.',
        tags: ['Dashboard'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organizer dashboard overview',
                content: new OA\JsonContent(
                    example: OrganizerDashboardResponseExamples::DASHBOARD,
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'kpis', ref: '#/components/schemas/OrganizerDashboardKpis'),
                                new OA\Property(property: 'today', ref: '#/components/schemas/OrganizerDashboardToday'),
                                new OA\Property(property: 'events', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrganizerDashboardEvent')),
                                new OA\Property(property: 'latest_orders', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrganizerDashboardOrder')),
                                new OA\Property(property: 'latest_check_ins', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrganizerDashboardCheckIn')),
                                new OA\Property(property: 'commission', ref: '#/components/schemas/OrganizerDashboardCommission'),
                                new OA\Property(property: 'meta', ref: '#/components/schemas/OrganizerDashboardMeta'),
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
