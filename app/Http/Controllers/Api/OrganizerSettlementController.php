<?php

namespace App\Http\Controllers\Api;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Http\Requests\Settlements\OrganizerSettlementEntriesRequest;
use App\Http\Requests\Settlements\OrganizerSettlementSummaryRequest;
use App\Services\Settlements\SettlementLedgerService;
use App\Services\Settlements\SettlementMetaService;
use App\Services\Settlements\SettlementSummaryService;
use App\Support\Http\Settlements\SettlementApiResponse;
use Illuminate\Http\JsonResponse;

class OrganizerSettlementController extends BaseApiController
{
    public function __construct(
        private readonly SettlementSummaryService $summaryService,
        private readonly SettlementLedgerService $ledgerService,
        private readonly TenantContextInterface $tenantContext,
        private readonly SettlementMetaService $metaService,
    ) {}

    public function summary(OrganizerSettlementSummaryRequest $request): JsonResponse
    {
        $venueId = $this->tenantContext->requireVenueId();
        $range = $request->dateRange();
        $summary = $this->summaryService->summarize($venueId, $range);

        return SettlementApiResponse::statement(
            summary: $summary,
            entries: [],
            payments: [],
            meta: $this->metaService->build($range, $venueId),
        );
    }

    public function entries(OrganizerSettlementEntriesRequest $request): JsonResponse
    {
        $venueId = $this->tenantContext->requireVenueId();
        $range = $request->dateRange();
        $paginator = $this->ledgerService->paginateEntries(
            venueId: $venueId,
            range: $range,
            perPage: $request->perPage(),
            page: $request->page(),
        );

        return SettlementApiResponse::statement(
            summary: null,
            entries: SettlementApiResponse::entriesPaginator($paginator),
            payments: [],
            meta: $this->metaService->build($range, $venueId, $paginator),
        );
    }
}
