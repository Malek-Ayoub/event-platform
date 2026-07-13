<?php

namespace App\Services\Settlements;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\SettlementEntry;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\Data\SettlementLedgerEntryData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class SettlementLedgerService
{
    public function __construct(
        private SettlementSummaryService $summaryService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, SettlementLedgerEntryData>
     */
    public function paginateEntries(
        int $venueId,
        SettlementDateRange $range,
        int $perPage = 15,
        int $page = 1,
    ): LengthAwarePaginator {
        $runningBalance = $this->openingBalance($venueId, $range->from);
        $entriesBeforeRange = $this->entriesBeforeRange($venueId, $range);

        foreach ($entriesBeforeRange as $entry) {
            $runningBalance = $this->applyEntry($runningBalance, $entry);
        }

        $entriesInRange = SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->when($range->from !== null, fn ($query) => $query->where('occurred_at', '>=', $range->from))
            ->when($range->to !== null, fn ($query) => $query->where('occurred_at', '<=', $range->to))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $mapped = $this->mapEntriesWithBalance($entriesInRange, $runningBalance);
        $total = $mapped->count();
        $offset = max(0, ($page - 1) * $perPage);
        $items = $mapped->slice($offset, $perPage)->values()->all();

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @return Collection<int, SettlementLedgerEntryData>
     */
    public function listEntries(int $venueId, SettlementDateRange $range): Collection
    {
        $runningBalance = $this->openingBalance($venueId, $range->from);
        $entriesBeforeRange = $this->entriesBeforeRange($venueId, $range);

        foreach ($entriesBeforeRange as $entry) {
            $runningBalance = $this->applyEntry($runningBalance, $entry);
        }

        $entriesInRange = SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->when($range->from !== null, fn ($query) => $query->where('occurred_at', '>=', $range->from))
            ->when($range->to !== null, fn ($query) => $query->where('occurred_at', '<=', $range->to))
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        return $this->mapEntriesWithBalance($entriesInRange, $runningBalance);
    }

    public function openingBalance(int $venueId, ?\Illuminate\Support\Carbon $from = null): string
    {
        if ($from === null) {
            return '0.00';
        }

        return $this->summaryService->outstandingCommission(
            $venueId,
            $from->copy()->subSecond(),
        );
    }

    /**
     * @return Collection<int, SettlementEntry>
     */
    private function entriesBeforeRange(int $venueId, SettlementDateRange $range): Collection
    {
        if ($range->from === null) {
            return collect();
        }

        return SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->where('occurred_at', '<', $range->from)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, SettlementEntry>  $entries
     * @return Collection<int, SettlementLedgerEntryData>
     */
    private function mapEntriesWithBalance(Collection $entries, string $runningBalance): Collection
    {
        return $entries->map(function (SettlementEntry $entry) use (&$runningBalance): SettlementLedgerEntryData {
            $credit = '0.00';
            $debit = '0.00';

            if ($entry->direction === SettlementEntryDirection::Credit) {
                $credit = $this->formatAmount($entry->amount);
            } else {
                $debit = $this->formatAmount($entry->amount);
            }

            $runningBalance = $this->applyEntry($runningBalance, $entry);

            return new SettlementLedgerEntryData(
                id: (int) $entry->id,
                date: $entry->occurred_at->toIso8601String(),
                type: $entry->type->value,
                description: $this->describeEntry($entry),
                credit: $credit,
                debit: $debit,
                balance: $runningBalance,
                orderId: $entry->order_id,
                eventId: $entry->event_id,
            );
        });
    }

    private function applyEntry(string $balance, SettlementEntry $entry): string
    {
        $amount = $this->formatAmount($entry->amount);

        if ($entry->direction === SettlementEntryDirection::Credit) {
            return bcadd($balance, $amount, 2);
        }

        return bcsub($balance, $amount, 2);
    }

    private function describeEntry(SettlementEntry $entry): string
    {
        return match ($entry->type) {
            SettlementEntryType::CommissionDue => $entry->order_id !== null
                ? "Platform commission on order #{$entry->order_id}"
                : 'Platform commission due',
            SettlementEntryType::CommissionAdjustment => $entry->order_id !== null
                ? "Commission adjustment on order #{$entry->order_id}"
                : 'Commission adjustment after refund',
            SettlementEntryType::CommissionPaid => 'Commission payment received',
        };
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
