<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Events\CreateTicketTypeRequest;
use App\Http\Requests\Events\DeleteTicketTypeRequest;
use App\Http\Requests\Events\ListTicketTypesRequest;
use App\Http\Requests\Events\ShowTicketTypeRequest;
use App\Http\Requests\Events\UpdateTicketTypeRequest;
use App\Http\Resources\Events\TicketTypeResource;
use App\Services\Events\TicketTypeService;
use App\Support\Http\Events\EventRequestMapper;
use Illuminate\Http\JsonResponse;

class TicketTypeController extends BaseApiController
{
    public function __construct(
        private readonly TicketTypeService $ticketTypeService,
    ) {}

    public function index(ListTicketTypesRequest $request): JsonResponse
    {
        $paginator = $this->ticketTypeService->listForEvent($request->routeEvent(), $request->perPage());

        return $this->respondPaginated(
            TicketTypeResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateTicketTypeRequest $request): JsonResponse
    {
        $ticketType = $this->ticketTypeService->createTicketType(
            EventRequestMapper::toCreateTicketTypeData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new TicketTypeResource($ticketType));
    }

    public function show(ShowTicketTypeRequest $request): JsonResponse
    {
        return $this->respondResource(new TicketTypeResource($request->routeTicketType()));
    }

    public function update(UpdateTicketTypeRequest $request): JsonResponse
    {
        $updated = $this->ticketTypeService->updateTicketType(
            $request->routeTicketType(),
            EventRequestMapper::toUpdateTicketTypeData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new TicketTypeResource($updated));
    }

    public function destroy(DeleteTicketTypeRequest $request): JsonResponse
    {
        $this->ticketTypeService->deleteTicketType(
            $request->routeTicketType(),
            $request->user(),
            $request->ip(),
        );

        return $this->respondPlainMessage('Ticket type deleted successfully.');
    }
}
