<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for public catalog routes. */
final class PublicEventPaths
{
    #[OA\Get(
        path: '/api/public/events',
        operationId: 'public.events.index',
        summary: 'List published events (public catalog)',
        description: 'Public, unauthenticated catalog of published events for the current venue context. Only events with status `published` are included. No Authorization header is required.',
        tags: ['Public'],
        security: [],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 12),
            ),
            new OA\Parameter(
                name: 'sort',
                in: 'query',
                schema: new OA\Schema(type: 'string', example: 'starts_at', default: 'starts_at'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated published events',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PublicEventListItem')),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function index(): void {}
}
