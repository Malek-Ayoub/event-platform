<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant order routes. */
final class TenantOrderPaths
{
    #[OA\Get(
        path: '/api/tenant/orders',
        operationId: 'tenant.orders.index',
        summary: 'List orders',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'event_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'paid', 'failed', 'refunded', 'cancelled'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated orders',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/OrderResource')),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function index(): void {}

    #[OA\Post(
        path: '/api/tenant/orders',
        operationId: 'tenant.orders.store',
        summary: 'Create order',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateOrderRequest')),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Order created',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/OrderResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: 'Validation or business rule error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/tenant/orders/{order}',
        operationId: 'tenant.orders.show',
        summary: 'Show order',
        tags: ['Orders'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Order details',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/OrderResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(): void {}
}
