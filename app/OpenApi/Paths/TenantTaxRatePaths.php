<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant tax rate routes. */
final class TenantTaxRatePaths
{
    #[OA\Get(path: '/api/tenant/tax-rates', operationId: 'tenant.tax-rates.index', summary: 'List tax rates', tags: ['Platform'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))], responses: [new OA\Response(response: 200, description: 'Paginated tax rates', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TaxRateResource')), new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'), new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks')], type: 'object'))])]
    public function index(): void {}

    #[OA\Post(path: '/api/tenant/tax-rates', operationId: 'tenant.tax-rates.store', summary: 'Create tax rate', tags: ['Platform'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'rate'], properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'rate', type: 'number', minimum: 0, maximum: 1), new OA\Property(property: 'is_active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TaxRateResource')], type: 'object'))])]
    public function store(): void {}

    #[OA\Get(path: '/api/tenant/tax-rates/{taxRate}', operationId: 'tenant.tax-rates.show', summary: 'Show tax rate', tags: ['Platform'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'taxRate', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TaxRateResource')], type: 'object'))])]
    public function show(): void {}

    #[OA\Put(path: '/api/tenant/tax-rates/{taxRate}', operationId: 'tenant.tax-rates.update', summary: 'Update tax rate', tags: ['Platform'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'taxRate', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['version'], properties: [new OA\Property(property: 'version', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'rate', type: 'number'), new OA\Property(property: 'is_active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TaxRateResource')], type: 'object')), new OA\Response(response: 409, description: 'Stale version', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))])]
    public function update(): void {}

    #[OA\Delete(path: '/api/tenant/tax-rates/{taxRate}', operationId: 'tenant.tax-rates.destroy', summary: 'Delete tax rate', tags: ['Platform'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'taxRate', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'))])]
    public function destroy(): void {}
}
