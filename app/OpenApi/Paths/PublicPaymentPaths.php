<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for public guest payment routes. */
final class PublicPaymentPaths
{
    #[OA\Post(
        path: '/api/public/orders/{orderNumber}/payment-instructions',
        operationId: 'public.orders.payment-instructions',
        summary: 'Create guest payment instructions',
        description: 'Public, unauthenticated payment instructions for a pending order identified by order_number. Rate limited to 10 requests per minute per IP. No Authorization header is required. Does not expose numeric payment ids.',
        tags: ['Public'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'orderNumber',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'ORD-ABCDEFGHIJ'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment instructions created or reused (idempotent)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/PublicPaymentInstructionResource'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Order not found, not pending, or not in this venue',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
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
    public function instructions(): void {}

    #[OA\Post(
        path: '/api/public/orders/{orderNumber}/payment-verification',
        operationId: 'public.orders.payment-verification',
        summary: 'Verify guest payment by transaction number',
        description: 'Public, unauthenticated verification of a wallet transfer for a pending order. Looks up the latest awaiting_transfer/verifying payment internally — payment ids are never accepted from the client. Rate limited to 10 requests per minute per IP.',
        tags: ['Public'],
        security: [],
        parameters: [
            new OA\Parameter(
                name: 'orderNumber',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'ORD-ABCDEFGHIJ'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/SubmitPublicPaymentVerificationRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verification result',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', ref: '#/components/schemas/PublicPaymentVerificationResource'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Order or active payment instruction not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
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
    public function verify(): void {}
}
