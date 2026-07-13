<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Requests\Dashboard\OrganizerDashboardRequest;
use App\Services\Dashboard\OrganizerDashboardService;
use App\Support\Http\Dashboard\OrganizerDashboardApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizerDashboardController extends BaseApiController
{
    public function __construct(
        private readonly OrganizerDashboardService $dashboardService,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    public function show(OrganizerDashboardRequest $request): JsonResponse
    {
        $dashboard = $this->dashboardService->build(
            $this->tenantContext->requireVenueId(),
        );

        return OrganizerDashboardApiResponse::dashboard($dashboard);
    }
}
