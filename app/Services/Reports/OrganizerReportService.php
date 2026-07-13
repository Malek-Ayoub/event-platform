<?php

namespace App\Services\Reports;

use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\SettlementEntry;
use App\Models\Ticket;
use App\Services\Reports\Data\OrganizerReportData;
use App\Services\Reports\Data\OrganizerReportFilter;
use App\Services\Settlements\Data\SettlementDateRange;
use App\Services\Settlements\SettlementSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class OrganizerReportService
{
    public function __construct(
        private SettlementSummaryService $settlementSummaryService,
    ) {}

    public function build(OrganizerReportFilter $filter): OrganizerReportData
    {
        if ($filter->eventId !== null) {
            $this->resolveEvent($filter->venueId, $filter->eventId);
        }

        $currency = $this->settlementSummaryService->resolveCurrency($filter->venueId);
        $grossSales = $this->sumGrossSales($filter);
        $ordersCount = $this->countOrders($filter);
        $ticketsSold = $this->countTicketsIssued($filter);
        $refundedAmount = $this->sumRefunds($filter);
        $checkedIn = $this->countCheckedIn($filter);
        $commission = $this->buildCommissionSection($filter);

        return new OrganizerReportData(
            sales: [
                'gross_sales' => $grossSales,
                'orders_count' => $ordersCount,
                'tickets_sold' => $ticketsSold,
                'average_order_value' => $this->averageOrderValue($grossSales, $ordersCount),
            ],
            revenue: [
                'gross_revenue' => $grossSales,
                'refunded_amount' => $refundedAmount,
                'net_revenue' => $this->subtractAmounts($grossSales, $refundedAmount),
            ],
            attendance: [
                'tickets_issued' => $ticketsSold,
                'checked_in' => $checkedIn,
                'attendance_rate' => $this->attendanceRate($checkedIn, $ticketsSold),
            ],
            commission: $commission,
            meta: [
                'from' => $filter->range->from?->toIso8601String(),
                'to' => $filter->range->to?->toIso8601String(),
                'currency' => $currency,
                'event_id' => $filter->eventId,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildCommissionSection(OrganizerReportFilter $filter): array
    {
        if ($filter->eventId === null) {
            $summary = $this->settlementSummaryService->summarize($filter->venueId, $filter->range);

            return [
                'commission_due' => $summary->commissionDue,
                'commission_paid' => $summary->commissionPaid,
                'outstanding_commission' => $summary->commissionOutstanding,
            ];
        }

        $due = $this->sumSettlementAmount($filter, SettlementEntryType::CommissionDue);
        $paid = $this->sumSettlementAmount($filter, SettlementEntryType::CommissionPaid);
        $outstanding = $this->outstandingCommissionForEvent(
            $filter->venueId,
            $filter->eventId,
            $filter->range->to,
        );

        return [
            'commission_due' => $due,
            'commission_paid' => $paid,
            'outstanding_commission' => $outstanding,
        ];
    }

    private function resolveEvent(int $venueId, int $eventId): Event
    {
        $event = Event::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->whereKey($eventId)
            ->first();

        if ($event === null) {
            throw (new ModelNotFoundException)->setModel(Event::class, [$eventId]);
        }

        return $event;
    }

    private function sumGrossSales(OrganizerReportFilter $filter): string
    {
        $query = $this->orderQuery($filter)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded]);

        $this->applyDateRange($query, 'updated_at', $filter->range);

        return $this->formatAmount($query->sum('total'));
    }

    private function countOrders(OrganizerReportFilter $filter): int
    {
        $query = $this->orderQuery($filter)
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded]);

        $this->applyDateRange($query, 'updated_at', $filter->range);

        return (int) $query->count();
    }

    private function countTicketsIssued(OrganizerReportFilter $filter): int
    {
        $query = $this->ticketQuery($filter)
            ->whereNotIn('status', [
                TicketStatus::Cancelled,
                TicketStatus::Invalidated,
            ]);

        $this->applyDateRange($query, 'issued_at', $filter->range);

        return (int) $query->count();
    }

    private function countCheckedIn(OrganizerReportFilter $filter): int
    {
        $query = $this->ticketQuery($filter)
            ->where('status', TicketStatus::CheckedIn)
            ->whereNotNull('checked_in_at');

        $this->applyDateRange($query, 'checked_in_at', $filter->range);

        return (int) $query->count();
    }

    private function sumRefunds(OrganizerReportFilter $filter): string
    {
        $query = Refund::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $filter->venueId)
            ->where('status', RefundStatus::Processed);

        if ($filter->eventId !== null) {
            $query->whereHas('order', fn (Builder $orderQuery) => $orderQuery
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('event_id', $filter->eventId));
        }

        $this->applyDateRange($query, 'processed_at', $filter->range);

        return $this->formatAmount($query->sum('amount'));
    }

    private function sumSettlementAmount(OrganizerReportFilter $filter, SettlementEntryType $type): string
    {
        $query = SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $filter->venueId)
            ->where('type', $type);

        if ($filter->eventId !== null) {
            $query->where('event_id', $filter->eventId);
        }

        $this->applyDateRange($query, 'occurred_at', $filter->range);

        return $this->formatAmount($query->sum('amount'));
    }

    private function outstandingCommissionForEvent(
        int $venueId,
        int $eventId,
        ?Carbon $asOf = null,
    ): string {
        $range = new SettlementDateRange(to: $asOf);
        $filter = new OrganizerReportFilter($venueId, $range, $eventId);

        $due = $this->sumSettlementAmount($filter, SettlementEntryType::CommissionDue);
        $adjustments = $this->sumSettlementAmount($filter, SettlementEntryType::CommissionAdjustment);
        $paid = $this->sumSettlementAmount($filter, SettlementEntryType::CommissionPaid);

        return $this->subtractAmounts($due, $this->addAmounts($adjustments, $paid));
    }

    /**
     * @return Builder<Order>
     */
    private function orderQuery(OrganizerReportFilter $filter): Builder
    {
        $query = Order::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $filter->venueId);

        if ($filter->eventId !== null) {
            $query->where('event_id', $filter->eventId);
        }

        return $query;
    }

    /**
     * @return Builder<Ticket>
     */
    private function ticketQuery(OrganizerReportFilter $filter): Builder
    {
        $query = Ticket::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $filter->venueId);

        if ($filter->eventId !== null) {
            $query->where('event_id', $filter->eventId);
        }

        return $query;
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

    private function averageOrderValue(string $grossSales, int $ordersCount): string
    {
        if ($ordersCount === 0) {
            return '0.00';
        }

        return bcdiv($this->formatAmount($grossSales), (string) $ordersCount, 2);
    }

    private function attendanceRate(int $checkedIn, int $ticketsIssued): string
    {
        if ($ticketsIssued === 0) {
            return '0.00';
        }

        return bcdiv(bcmul((string) $checkedIn, '100', 4), (string) $ticketsIssued, 2);
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
