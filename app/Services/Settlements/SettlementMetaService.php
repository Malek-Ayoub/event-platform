<?php

namespace App\Services\Settlements;

use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\Data\SettlementStatementMetaData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SettlementMetaService
{
    public function __construct(
        private SettlementSummaryService $summaryService,
        private SettlementLedgerService $ledgerService,
    ) {}

    public function build(
        SettlementDateRange $range,
        int $venueId,
        ?LengthAwarePaginator $entries = null,
    ): SettlementStatementMetaData {
        return new SettlementStatementMetaData(
            from: $range->from?->toIso8601String(),
            to: $range->to?->toIso8601String(),
            currency: $this->summaryService->resolveCurrency($venueId),
            openingBalance: $this->ledgerService->openingBalance($venueId, $range->from),
            pagination: $entries !== null ? [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ] : [],
        );
    }
}
