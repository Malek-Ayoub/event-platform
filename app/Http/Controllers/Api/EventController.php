<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Events\ArchiveEventRequest;
use App\Http\Requests\Events\CreateEventRequest;
use App\Http\Requests\Events\DeleteEventRequest;
use App\Http\Requests\Events\ListEventsRequest;
use App\Http\Requests\Events\PublishEventRequest;
use App\Http\Requests\Events\ShowEventRequest;
use App\Http\Requests\Events\UpdateEventRequest;
use App\Http\Resources\Events\EventResource;
use App\Services\Events\EventService;
use App\Support\Http\Events\EventRequestMapper;
use Illuminate\Http\JsonResponse;

class EventController extends BaseApiController
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    public function index(ListEventsRequest $request): JsonResponse
    {
        $paginator = $this->eventService->list($request->perPage());

        return $this->respondPaginated(
            EventResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateEventRequest $request): JsonResponse
    {
        $event = $this->eventService->createEvent(
            EventRequestMapper::toCreateEventData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondCreated(new EventResource($event));
    }

    public function show(ShowEventRequest $request): JsonResponse
    {
        $event = $request->routeEvent();
        $event?->loadMissing('category');

        return $this->respondResource(new EventResource($event));
    }

    public function update(UpdateEventRequest $request): JsonResponse
    {
        $updated = $this->eventService->updateEvent(
            $request->routeEvent(),
            EventRequestMapper::toUpdateEventData($request->toDto(), $request->user(), $request->ip()),
        );

        return $this->respondResource(new EventResource($updated));
    }

    public function destroy(DeleteEventRequest $request): JsonResponse
    {
        $this->eventService->deleteEvent($request->routeEvent(), $request->user(), $request->ip());

        return $this->respondPlainMessage('Event deleted successfully.');
    }

    public function publish(PublishEventRequest $request): JsonResponse
    {
        $published = $this->eventService->publishEvent(
            $request->routeEvent(),
            $request->user(),
            $request->ip(),
        );

        return $this->respondResource(new EventResource($published));
    }

    public function archive(ArchiveEventRequest $request): JsonResponse
    {
        $archived = $this->eventService->archiveEvent(
            $request->routeEvent(),
            $request->user(),
            $request->ip(),
        );

        return $this->respondResource(new EventResource($archived));
    }
}
