<?php

namespace App\Support\Http\Settlements;

use App\Services\Settlements\Data\SettlementLedgerEntryData;
use App\Services\Settlements\Data\SettlementStatementMetaData;
use App\Services\Settlements\Data\SettlementSummaryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

final class SettlementApiResponse
{
    /**
     * @param  list<array<string, mixed>>  $entries
     * @param  list<array<string, mixed>>  $payments
     */
    public static function statement(
        ?SettlementSummaryData $summary,
        array $entries,
        array $payments,
        SettlementStatementMetaData $meta,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'data' => [
                'summary' => $summary?->toArray() ?? [],
                'entries' => $entries,
                'payments' => $payments,
                'meta' => $meta->toArray(),
            ],
        ], $status);
    }

    /**
     * @param  LengthAwarePaginator<int, SettlementLedgerEntryData>  $paginator
     */
    public static function entriesPaginator(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (SettlementLedgerEntryData $entry): array => $entry->toArray())
            ->all();
    }

    /**
     * @param  LengthAwarePaginator<int, array<string, mixed>>  $paginator
     */
    public static function venueListPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ];
    }
}
