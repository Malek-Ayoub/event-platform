<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Venues\CreateVenueRequest;
use App\Http\Resources\Venues\VenueResource;
use App\Services\Venues\Data\CreateVenueData;
use App\Services\Venues\VenueService;
use Illuminate\Http\JsonResponse;

class VenueController extends BaseApiController
{
    public function __construct(
        private readonly VenueService $venueService,
    ) {}

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
}
