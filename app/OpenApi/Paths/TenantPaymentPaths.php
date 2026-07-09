<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant payment routes. */
final class TenantPaymentPaths
{
    #[OA\Get(
        path: '/api/tenant/payments',
        operationId: 'tenant.payments.index',
        summary: 'List payment transactions',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100)),
            new OA\Parameter(name: 'order_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: [
                'pending',
                'completed',
                'failed',
                'refunded',
                'awaiting_transfer',
                'verifying',
                'paid',
                'expired',
            ])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated payments',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/PaymentTransactionResource')),
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
        path: '/api/tenant/payments',
        operationId: 'tenant.payments.store',
        summary: 'Create payment instructions for manual wallet transfer',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/InitiatePaymentRequest')),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Payment instructions created',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentInstructionResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: 'Validation or business rule error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function store(): void {}

    #[OA\Get(
        path: '/api/tenant/payments/{paymentTransaction}',
        operationId: 'tenant.payments.show',
        summary: 'Show payment transaction',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentTransaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment details',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentTransactionResource')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function show(): void {}

    #[OA\Post(
        path: '/api/tenant/payments/{paymentTransaction}/verify',
        operationId: 'tenant.payments.verify',
        summary: 'Verify manual wallet transfer by transaction number',
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentTransaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/VerifyPaymentRequest')),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment verification result',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentTransactionResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: 'Validation, duplicate transaction number, or verification failure', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function verify(): void {}

    #[OA\Post(
        path: '/api/tenant/payments/{paymentTransaction}/complete',
        operationId: 'tenant.payments.complete',
        summary: 'Complete payment (deprecated — use verify)',
        deprecated: true,
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentTransaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'payment_method', type: 'string', maxLength: 50, nullable: true),
                    new OA\Property(property: 'payment_reference', type: 'string', maxLength: 255, nullable: true),
                ],
                type: 'object',
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment completed',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentTransactionResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 422, description: 'Invalid state transition', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ],
    )]
    public function complete(): void {}

    #[OA\Post(
        path: '/api/tenant/payments/{paymentTransaction}/fail',
        operationId: 'tenant.payments.fail',
        summary: 'Mark payment as failed (deprecated — use verify)',
        deprecated: true,
        tags: ['Payments'],
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'paymentTransaction', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payment failed',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/PaymentTransactionResource')],
                    type: 'object',
                ),
            ),
        ],
    )]
    public function fail(): void {}
}
