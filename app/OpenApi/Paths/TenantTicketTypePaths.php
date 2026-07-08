<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/** OpenAPI path projections for tenant ticket type routes. */
final class TenantTicketTypePaths
{
    #[OA\Get(path: '/api/tenant/events/{event}/ticket-types', operationId: 'tenant.events.ticket-types.index', summary: 'List ticket types for event', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))], responses: [new OA\Response(response: 200, description: 'Paginated ticket types', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TicketTypeResource')), new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'), new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks')], type: 'object'))])]
    public function index(): void {}

    #[OA\Post(path: '/api/tenant/events/{event}/ticket-types', operationId: 'tenant.events.ticket-types.store', summary: 'Create ticket type', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'price', 'quantity'], properties: [new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'price', type: 'number'), new OA\Property(property: 'quantity', type: 'integer', minimum: 1), new OA\Property(property: 'sale_start', type: 'string', format: 'date-time', nullable: true), new OA\Property(property: 'sale_end', type: 'string', format: 'date-time', nullable: true), new OA\Property(property: 'benefits', type: 'array', items: new OA\Items(type: 'string')), new OA\Property(property: 'color', type: 'string', nullable: true)], type: 'object')), responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TicketTypeResource')], type: 'object'))])]
    public function store(): void {}

    #[OA\Get(path: '/api/tenant/ticket-types/{ticketType}', operationId: 'tenant.ticket-types.show', summary: 'Show ticket type', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'ticketType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TicketTypeResource')], type: 'object'))])]
    public function show(): void {}

    #[OA\Put(path: '/api/tenant/ticket-types/{ticketType}', operationId: 'tenant.ticket-types.update', summary: 'Update ticket type', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'ticketType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['version'], properties: [new OA\Property(property: 'version', type: 'integer'), new OA\Property(property: 'name', type: 'string'), new OA\Property(property: 'price', type: 'number'), new OA\Property(property: 'quantity', type: 'integer')], type: 'object')), responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TicketTypeResource')], type: 'object')), new OA\Response(response: 409, description: 'Stale version', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))])]
    public function update(): void {}

    #[OA\Delete(path: '/api/tenant/ticket-types/{ticketType}', operationId: 'tenant.ticket-types.destroy', summary: 'Delete ticket type', tags: ['Events'], security: [['sanctum' => []]], parameters: [new OA\Parameter(name: 'ticketType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))], responses: [new OA\Response(response: 200, description: 'Deleted', content: new OA\JsonContent(ref: '#/components/schemas/MessageResponse'))])]
    public function destroy(): void {}
}
