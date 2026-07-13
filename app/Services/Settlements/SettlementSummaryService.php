<?php

namespace App\Services\Settlements;

use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\SettlementEntry;
use App\Models\Ticket;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\Data\SettlementSummaryData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SettlementSummaryService
{
    public function summarize(int $venueId, SettlementDateRange $range): SettlementSummaryData
    {
        $currency = $this->resolveCurrency($venueId);

        $grossSales = $this->sumGrossSales($venueId, $range);
        $ticketsSold = $this->countTicketsSold($venueId, $range);
        $refunds = $this->sumRefunds($venueId, $range);
        $commissionDue = $this->sumSettlementAmount($venueId, SettlementEntryType::CommissionDue, $range);
        $commissionAdjustments = $this->sumSettlementAmount($venueId, SettlementEntryType::CommissionAdjustment, $range);
        $commissionPaid = $this->sumSettlementAmount($venueId, SettlementEntryType::CommissionPaid, $range);
        $commissionOutstanding = $this->outstandingCommission($venueId, $range->to);

        return new SettlementSummaryData(
            grossSales: $grossSales,
            ticketsSold: $ticketsSold,
            commissionDue: $commissionDue,
            commissionPaid: $commissionPaid,
            commissionAdjustments: $commissionAdjustments,
            commissionOutstanding: $commissionOutstanding,
            refunds: $refunds,
            netSales: $this->subtractAmounts($grossSales, $refunds),
            currency: $currency,
        );
    }

    public function outstandingCommission(int $venueId, ?Carbon $asOf = null): string
    {
        $due = $this->sumSettlementAmount($venueId, SettlementEntryType::CommissionDue, $this->asOfRange($asOf));
        $adjustments = $this->sumSettlementAmount($venueId, SettlementEntryType::CommissionAdjustment, $this->asOfRange($asOf));
        $paid = $this->sumSettlementAmount($venueId, SettlementEntryType::CommissionPaid, $this->asOfRange($asOf));

        return $this->subtractAmounts($due, $this->addAmounts($adjustments, $paid));
    }

    public function resolveCurrency(int $venueId): string
    {
        $currency = $this->settlementEntryQuery($venueId)
            ->orderByDesc('id')
            ->value('currency');

        return $currency !== null ? (string) $currency : 'USD';
    }

    private function sumGrossSales(int $venueId, SettlementDateRange $range): string
    {
        $query = Order::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded]);

        $this->applyDateRange($query, 'updated_at', $range);

        return $this->formatAmount($query->sum('total'));
    }

    private function countTicketsSold(int $venueId, SettlementDateRange $range): int
    {
        $query = Ticket::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->whereNotIn('status', [
                TicketStatus::Cancelled,
                TicketStatus::Invalidated,
            ]);

        $this->applyDateRange($query, 'issued_at', $range);

        return (int) $query->count();
    }

    private function sumRefunds(int $venueId, SettlementDateRange $range): string
    {
        $query = Refund::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->where('status', RefundStatus::Processed);

        $this->applyDateRange($query, 'processed_at', $range);

        return $this->formatAmount($query->sum('amount'));
    }

    private function sumSettlementAmount(
        int $venueId,
        SettlementEntryType $type,
        SettlementDateRange $range,
    ): string {
        $query = $this->settlementEntryQuery($venueId)
            ->where('type', $type);

        $this->applyDateRange($query, 'occurred_at', $range);

        return $this->formatAmount($query->sum('amount'));
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyDateRange(Builder $query, string $column, SettlementDateRange $range): void
    {
        if ($range->from !== null) {
            $query->where($column, '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where($column, '<=', $range->to);
        }
    }

    private function asOfRange(?Carbon $asOf): SettlementDateRange
    {
        return new SettlementDateRange(to: $asOf);
    }

    /**
     * @return Builder<SettlementEntry>
     */
    private function settlementEntryQuery(int $venueId): Builder
    {
        return SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId);
    }

    private function addAmounts(string $left, string $right): string
    {
        return bcadd($this->formatAmount($left), $this->formatAmount($right), 2);
    }

    private function subtractAmounts(string $left, string $right): string
    {
        return bcsub($this->formatAmount($left), $this->formatAmount($right), 2);
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
