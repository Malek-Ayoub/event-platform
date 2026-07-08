<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant event routes. */
final class TenantEventPaths
{
    #[OA\Get(
        path: '/api/tenant/events',
        operationId: 'tenant.events.index',
        summary: 'List events',
        tags: ['Events'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated events',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/EventResource')),
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
        path: '/api/tenant/events',
        operationId: 'tenant.events.store',
        summary: 'Create event',
        tags: ['Events'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CreateEventRequest')),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Event created',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/tenant/events/{event}',
        operationId: 'tenant.events.show',
        summary: 'Show event',
        tags: ['Events'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event details',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function show(): void {}

    #[OA\Put(
        path: '/api/tenant/events/{event}',
        operationId: 'tenant.events.update',
        summary: 'Update event',
        tags: ['Events'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['version'],
                properties: [
                    new OA\Property(property: 'version', type: 'integer', minimum: 1),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'start_datetime', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'end_datetime', type: 'string', format: 'date-time'),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event updated',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 409, description: 'Stale version', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function update(): void {}

    #[OA\Delete(
        path: '/api/tenant/events/{event}',
        operationId: 'tenant.events.destroy',
        summary: 'Delete event',
        tags: ['Events'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Event deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function destroy(): void {}

    #[OA\Post(
        path: '/api/tenant/events/{event}/publish',
        operationId: 'tenant.events.publish',
        summary: 'Publish event',
        tags: ['Events'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event published',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventResource')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function publish(): void {}

    #[OA\Post(
        path: '/api/tenant/events/{event}/archive',
        operationId: 'tenant.events.archive',
        summary: 'Archive event',
        tags: ['Events'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event archived',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/EventResource')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function archive(): void {}
}
