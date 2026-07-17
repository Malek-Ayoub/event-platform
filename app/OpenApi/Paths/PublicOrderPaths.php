<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for public guest order routes. */
final class PublicOrderPaths
{
    #[OA\Post(
        path: '/api/public/orders',
        operationId: 'public.orders.store',
        summary: 'Create a guest order (public checkout)',
        description: 'Public, unauthenticated order creation for a published event in the current venue context. Guests cannot set customer_user_id or reservation_id. Rate limited to 10 requests per minute per IP. No Authorization header is required.',
        tags: ['Public'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CreatePublicOrderRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Guest order created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/PublicOrderResource'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation or business rule error',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'),
            ),
            new OA\Response(
                response: 429,
                description: 'Too many requests',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function store(): void {}
}
