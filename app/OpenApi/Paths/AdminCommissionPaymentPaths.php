<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for platform admin `/api/admin/*` routes (Phase 8.5.3). */
final class AdminCommissionPaymentPaths
{
    #[OA\Post(
        path: '/api/admin/commission-payments',
        operationId: 'admin.commission-payments.store',
        summary: 'Record commission received from an organizer',
        tags: ['Admin'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RecordCommissionPaymentRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Commission payment recorded',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CommissionPaymentResource')],
                    type: 'object',
                ),
            ),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
            new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse')),
        ],
    )]
    public function storeCommissionPayment(): void {}
}
