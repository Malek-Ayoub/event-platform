<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Requests\Reports\OrganizerReportRequest;
use App\Services\Reports\Data\OrganizerReportFilter;
use App\Services\Reports\OrganizerReportService;
use App\Support\Http\Reports\OrganizerReportApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizerReportController extends BaseApiController
{
    public function __construct(
        private readonly OrganizerReportService $reportService,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    public function show(OrganizerReportRequest $request): JsonResponse
    {
        $report = $this->reportService->build(new OrganizerReportFilter(
            venueId: $this->tenantContext->requireVenueId(),
            range: $request->dateRange(),
            eventId: $request->eventId(),
        ));

        return OrganizerReportApiResponse::report($report);
    }
}
