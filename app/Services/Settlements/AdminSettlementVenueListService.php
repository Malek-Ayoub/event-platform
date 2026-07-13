<?php

namespace App\Services\Settlements;

use App\Enums\FinancialDomain\SettlementEntryType;
use App\Models\CommissionPayment;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Venue;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminSettlementVenueListService
{
    public function __construct(
        private SettlementSummaryService $summaryService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateVenues(
        SettlementDateRange $range,
        int $perPage = 15,
        int $page = 1,
        ?string $search = null,
        ?string $sort = null,
        string $direction = 'desc',
        ?string $minOutstanding = null,
    ): LengthAwarePaginator {
        $settlementTotals = DB::table('settlement_entries')
            ->select('venue_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as commission_due_total", [SettlementEntryType::CommissionDue->value])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as commission_adjustment_total", [SettlementEntryType::CommissionAdjustment->value])
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as commission_paid_total", [SettlementEntryType::CommissionPaid->value])
            ->when($range->from !== null, fn ($query) => $query->where('occurred_at', '>=', $range->from))
            ->when($range->to !== null, fn ($query) => $query->where('occurred_at', '<=', $range->to))
            ->groupBy('venue_id');

        $outstandingTotals = DB::table('settlement_entries')
            ->select('venue_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type IN (?, ?) THEN -amount ELSE 0 END), 0) as outstanding_commission", [
                SettlementEntryType::CommissionDue->value,
                SettlementEntryType::CommissionAdjustment->value,
                SettlementEntryType::CommissionPaid->value,
            ])
            ->when($range->to !== null, fn ($query) => $query->where('occurred_at', '<=', $range->to))
            ->groupBy('venue_id');

        $grossSales = DB::table('orders')
            ->select('venue_id')
            ->selectRaw('COALESCE(SUM(total), 0) as gross_sales')
            ->whereIn('status', ['paid', 'refunded'])
            ->when($range->from !== null, fn ($query) => $query->where('updated_at', '>=', $range->from))
            ->when($range->to !== null, fn ($query) => $query->where('updated_at', '<=', $range->to))
            ->groupBy('venue_id');

        $lastPayments = DB::table('commission_payments')
            ->select('venue_id')
            ->selectRaw('MAX(received_at) as last_payment_at')
            ->selectRaw('COALESCE(SUM(amount), 0) as commission_paid_in_period')
            ->when($range->from !== null, fn ($query) => $query->where('received_at', '>=', $range->from))
            ->when($range->to !== null, fn ($query) => $query->where('received_at', '<=', $range->to))
            ->groupBy('venue_id');

        $query = Venue::query()
            ->select([
                'venues.id',
                'venues.name',
                'venues.subdomain',
            ])
            ->leftJoinSub($settlementTotals, 'settlement_totals', 'venues.id', '=', 'settlement_totals.venue_id')
            ->leftJoinSub($outstandingTotals, 'outstanding_totals', 'venues.id', '=', 'outstanding_totals.venue_id')
            ->leftJoinSub($grossSales, 'gross_sales_totals', 'venues.id', '=', 'gross_sales_totals.venue_id')
            ->leftJoinSub($lastPayments, 'payment_totals', 'venues.id', '=', 'payment_totals.venue_id')
            ->selectRaw('COALESCE(settlement_totals.commission_due_total, 0) as commission_due')
            ->selectRaw('COALESCE(settlement_totals.commission_paid_total, 0) as commission_paid')
            ->selectRaw('COALESCE(outstanding_totals.outstanding_commission, 0) as outstanding_commission')
            ->selectRaw('COALESCE(gross_sales_totals.gross_sales, 0) as gross_sales')
            ->selectRaw('payment_totals.last_payment_at as last_payment_at');

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('venues.name', 'like', '%'.$search.'%')
                    ->orWhere('venues.subdomain', 'like', '%'.$search.'%');
            });
        }

        if ($minOutstanding !== null) {
            $query->whereRaw('COALESCE(outstanding_totals.outstanding_commission, 0) >= ?', [$minOutstanding]);
        }

        $sortColumn = match ($sort) {
            'gross_sales' => 'gross_sales',
            'commission_paid' => 'commission_paid',
            'last_payment' => 'last_payment_at',
            'outstanding', 'outstanding_commission' => 'outstanding_commission',
            default => 'venues.name',
        };

        $query->orderBy($sortColumn, $direction === 'asc' ? 'asc' : 'desc');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        /** @var Collection<int, object> $collection */
        $collection = $paginator->getCollection();

        $items = $collection->map(function (object $venue): array {
            return [
                'venue_id' => (int) $venue->id,
                'venue_name' => (string) $venue->name,
                'subdomain' => (string) $venue->subdomain,
                'gross_sales' => $this->formatAmount($venue->gross_sales ?? 0),
                'commission_due' => $this->formatAmount($venue->commission_due ?? 0),
                'commission_paid' => $this->formatAmount($venue->commission_paid ?? 0),
                'outstanding_commission' => $this->formatAmount($venue->outstanding_commission ?? 0),
                'last_payment_at' => $venue->last_payment_at !== null
                    ? (string) $venue->last_payment_at
                    : null,
                'currency' => $this->summaryService->resolveCurrency((int) $venue->id),
            ];
        })->all();

        return new Paginator(
            $items,
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentHistory(int $venueId, SettlementDateRange $range): array
    {
        $query = CommissionPayment::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('venue_id', $venueId)
            ->orderByDesc('received_at')
            ->orderByDesc('id');

        if ($range->from !== null) {
            $query->where('received_at', '>=', $range->from);
        }

        if ($range->to !== null) {
            $query->where('received_at', '<=', $range->to);
        }

        return $query->get()->map(fn (CommissionPayment $payment): array => [
            'id' => $payment->id,
            'amount' => (string) $payment->amount,
            'currency' => (string) $payment->currency,
            'payment_method' => $payment->payment_method->value,
            'reference_number' => $payment->reference_number,
            'received_at' => $payment->received_at?->toIso8601String(),
            'received_by_user_id' => $payment->received_by_user_id,
            'notes' => $payment->notes,
            'payment_account_id' => $payment->payment_account_id,
            'created_at' => $payment->created_at?->toIso8601String(),
        ])->all();
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
