<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Settlements\AdminSettlementVenueListRequest;
use App\Http\Requests\Settlements\AdminVenueSettlementRequest;
use App\Services\Settlements\AdminSettlementVenueListService;
use App\Services\Settlements\SettlementLedgerService;
use App\Services\Settlements\SettlementMetaService;
use App\Services\Settlements\SettlementSummaryService;
use App\Support\Http\Settlements\SettlementApiResponse;
use Illuminate\Http\JsonResponse;

class AdminSettlementController extends BaseApiController
{
    public function __construct(
        private readonly AdminSettlementVenueListService $venueListService,
        private readonly SettlementSummaryService $summaryService,
        private readonly SettlementLedgerService $ledgerService,
        private readonly SettlementMetaService $metaService,
    ) {}

    public function venues(AdminSettlementVenueListRequest $request): JsonResponse
    {
        $range = $request->dateRange();
        $paginator = $this->venueListService->paginateVenues(
            range: $range,
            perPage: $request->perPage(),
            page: $request->page(),
            search: $request->search(),
            sort: $request->sort(),
            direction: $request->direction(),
            minOutstanding: $request->minOutstanding(),
        );

        $payload = SettlementApiResponse::venueListPaginator($paginator);
        $payload['meta']['from'] = $range->from?->toIso8601String();
        $payload['meta']['to'] = $range->to?->toIso8601String();

        return response()->json($payload);
    }

    public function venueSettlement(AdminVenueSettlementRequest $request): JsonResponse
    {
        $venueId = $request->routeVenueId();
        $range = $request->dateRange();

        $summary = $this->summaryService->summarize($venueId, $range);
        $entries = $this->ledgerService->paginateEntries(
            venueId: $venueId,
            range: $range,
            perPage: $request->perPage(),
            page: $request->page(),
        );
        $payments = $this->venueListService->paymentHistory($venueId, $range);

        return SettlementApiResponse::statement(
            summary: $summary,
            entries: SettlementApiResponse::entriesPaginator($entries),
            payments: $payments,
            meta: $this->metaService->build($range, $venueId, $entries),
        );
    }
}
