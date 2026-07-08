<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant auth and ping routes. */
final class TenantAuthPaths
{
    #[OA\Get(
        path: '/api/tenant/ping',
        operationId: 'tenant.ping',
        summary: 'Tenant context ping',
        tags: ['Platform'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tenant resolved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'venue_id', type: 'integer'),
                        new OA\Property(property: 'source', type: 'string'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function ping(): void {}

    #[OA\Post(
        path: '/api/tenant/auth/login',
        operationId: 'tenant.auth.login',
        summary: 'Tenant-scoped login',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/LoginRequest')),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated for tenant',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/ApiTokenResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function tenantLogin(): void {}

    #[OA\Get(
        path: '/api/tenant/auth/user',
        operationId: 'tenant.auth.user',
        summary: 'Get authenticated user in tenant context',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current tenant user',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/AuthenticatedUserResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function tenantUser(): void {}

    #[OA\Post(
        path: '/api/tenant/auth/logout',
        operationId: 'tenant.auth.logout',
        summary: 'Revoke current token (tenant)',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
        ],
    )]
    public function tenantLogout(): void {}
}
