<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant category routes. */
final class TenantCategoryPaths
{
    #[OA\Get(path: '/api/tenant/categories', operationId: 'tenant.categories.index', summary: 'List categories', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))], responses: [new OA\Response(response: 200, description: 'Paginated categories', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CategoryResource')), new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'), new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks')], type: 'object'))])]
    public function index(): void {}

    #[OA\Post(path: '/api/tenant/categories', operationId: 'tenant.categories.store', summary: 'Create category', tags: ['Events'], security: [['sanctum' => []]], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'slug', type: 'string', nullable: true), new OA\Property(property: 'description', type: 'string', nullable: true), new OA\Property(property: 'sort_order', type: 'integer'), new OA\Property(property: 'is_active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CategoryResource')], type: 'object')), new OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'))])]
    public function store(): void {}

    #[OA\Get(path: '/api/tenant/categories/{category}', operationId: 'tenant.categories.show', summary: 'Show category', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CategoryResource')], type: 'object'))])]
    public function show(): void {}

    #[OA\Put(path: '/api/tenant/categories/{category}', operationId: 'tenant.categories.update', summary: 'Update category', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'slug', type: 'string', nullable: true), new OA\Property(property: 'description', type: 'string', nullable: true), new OA\Property(property: 'sort_order', type: 'integer'), new OA\Property(property: 'is_active', type: 'boolean')], type: 'object')), responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/CategoryResource')], type: 'object'))])]
    public function update(): void {}

    #[OA\Delete(path: '/api/tenant/categories/{category}', operationId: 'tenant.categories.destroy', summary: 'Delete category', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'))])]
    public function destroy(): void {}
}
