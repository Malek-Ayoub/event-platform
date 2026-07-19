<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Venues\ActivateVenueRequest;
use App\Http\Requests\Venues\CreateVenueRequest;
use App\Http\Requests\Venues\ListVenuesRequest;
use App\Http\Requests\Venues\ShowVenueRequest;
use App\Http\Requests\Venues\SuspendVenueRequest;
use App\Http\Requests\Venues\UpdateVenueRequest;
use App\Http\Resources\Venues\VenueResource;
use App\Models\Venue;
use App\Services\Venues\Data\CreateVenueData;
use App\Services\Venues\Data\UpdateVenueData;
use App\Services\Venues\VenueService;
use Illuminate\Http\JsonResponse;

class VenueController extends BaseApiController
{
    public function __construct(
        private readonly VenueService $venueService,
    ) {}

    public function index(ListVenuesRequest $request): JsonResponse
    {
        $paginator = $this->venueService->listVenues($request->perPage());

        return $this->respondPaginated(
            VenueResource::collection($paginator),
            $paginator,
        );
    }

    public function store(CreateVenueRequest $request): JsonResponse
    {
        $venue = $this->venueService->createVenue(new CreateVenueData(
            name: (string) $request->validated('name'),
            subdomain: (string) $request->validated('subdomain'),
            ownerName: (string) $request->validated('owner_name'),
            ownerEmail: (string) $request->validated('owner_email'),
            ownerPassword: (string) $request->validated('owner_password'),
            actor: $request->user(),
            ipAddress: $request->ip(),
        ));

        return $this->respondCreated(new VenueResource($venue));
    }

    public function show(ShowVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue = $this->venueService->getVenue($venue);

        return $this->respondResource(new VenueResource($venue));
    }

    public function update(UpdateVenueRequest $request, Venue $venue): JsonResponse
    {
        $validated = $request->validated();

        $venue = $this->venueService->updateVenue(
            $venue,
            new UpdateVenueData(
                actor: $request->user(),
                name: array_key_exists('name', $validated) ? (string) $validated['name'] : null,
                commissionRate: array_key_exists('commission_rate', $validated)
                    ? number_format((float) $validated['commission_rate'], 2, '.', '')
                    : null,
                ipAddress: $request->ip(),
            ),
        );

        return $this->respondResource(new VenueResource($venue));
    }

    public function suspend(SuspendVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue = $this->venueService->suspendVenue(
            $venue,
            $request->user(),
            $request->ip(),
        );

        return $this->respondResource(new VenueResource($venue));
    }

    public function activate(ActivateVenueRequest $request, Venue $venue): JsonResponse
    {
        $venue = $this->venueService->activateVenue(
            $venue,
            $request->user(),
            $request->ip(),
        );

        return $this->respondResource(new VenueResource($venue));
    }
}
