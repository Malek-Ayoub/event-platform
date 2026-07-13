<?php

namespace App\Services\Reports\Queries;

use App\Enums\FinancialDomain\SettlementEntryType;
use App\Services\Reports\Queries\Concerns\AppliesReportDateRange;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Facades\DB;

class TopVenuesQuery
{
    use AppliesReportDateRange;

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(SettlementDateRange $range, int $limit): array
    {
        $grossSales = DB::table('orders')
            ->select('venue_id')
            ->selectRaw('COALESCE(SUM(total), 0) as gross_sales')
            ->whereIn('status', ['paid', 'refunded']);

        $this->applyDateRange($grossSales, 'updated_at', $range);
        $grossSales->groupBy('venue_id');

        $commissionDue = DB::table('settlement_entries')
            ->select('venue_id')
            ->selectRaw('COALESCE(SUM(amount), 0) as commission_due')
            ->where('type', SettlementEntryType::CommissionDue->value);

        $this->applyDateRange($commissionDue, 'occurred_at', $range);
        $commissionDue->groupBy('venue_id');

        $outstanding = DB::table('settlement_entries')
            ->select('venue_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type IN (?, ?) THEN -amount ELSE 0 END), 0) as outstanding_commission',
                [
                    SettlementEntryType::CommissionDue->value,
                    SettlementEntryType::CommissionAdjustment->value,
                    SettlementEntryType::CommissionPaid->value,
                ],
            )
            ->groupBy('venue_id');

        if ($range->to !== null) {
            $outstanding->where('occurred_at', '<=', $range->to);
        }

        $rows = DB::table('venues')
            ->select([
                'venues.id as venue_id',
                'venues.name as venue_name',
                'venues.subdomain',
            ])
            ->leftJoinSub($grossSales, 'gross_sales_totals', 'venues.id', '=', 'gross_sales_totals.venue_id')
            ->leftJoinSub($commissionDue, 'commission_due_totals', 'venues.id', '=', 'commission_due_totals.venue_id')
            ->leftJoinSub($outstanding, 'outstanding_totals', 'venues.id', '=', 'outstanding_totals.venue_id')
            ->selectRaw('COALESCE(gross_sales_totals.gross_sales, 0) as gross_sales')
            ->selectRaw('COALESCE(commission_due_totals.commission_due, 0) as commission_due')
            ->selectRaw('COALESCE(outstanding_totals.outstanding_commission, 0) as outstanding_commission')
            ->orderByDesc('gross_sales')
            ->limit($limit)
            ->get();

        return $rows->map(fn (object $row): array => [
            'venue_id' => (int) $row->venue_id,
            'venue_name' => (string) $row->venue_name,
            'subdomain' => (string) $row->subdomain,
            'gross_sales' => $this->formatAmount($row->gross_sales ?? 0),
            'commission_due' => $this->formatAmount($row->commission_due ?? 0),
            'outstanding_commission' => $this->formatAmount($row->outstanding_commission ?? 0),
        ])->all();
    }
}
