<?php

namespace App\Services\Reports\Queries;

use App\Enums\FinancialDomain\SettlementEntryType;
use App\Services\Reports\Queries\Concerns\AppliesReportDateRange;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CommissionReportQuery
{
    use AppliesReportDateRange;

    /**
     * @return array<string, mixed>
     */
    public function execute(SettlementDateRange $range): array
    {
        $periodQuery = DB::table('settlement_entries');
        $this->applyDateRange($periodQuery, 'occurred_at', $range);

        $commissionDue = $this->sumByType(clone $periodQuery, SettlementEntryType::CommissionDue);
        $commissionPaid = $this->sumByType(clone $periodQuery, SettlementEntryType::CommissionPaid);
        $commissionAdjustments = $this->sumByType(clone $periodQuery, SettlementEntryType::CommissionAdjustment);

        $outstandingQuery = DB::table('settlement_entries');
        if ($range->to !== null) {
            $outstandingQuery->where('occurred_at', '<=', $range->to);
        }

        $outstandingCommission = $this->formatAmount(
            (float) $this->sumByType(clone $outstandingQuery, SettlementEntryType::CommissionDue)
            - (float) $this->sumByType(clone $outstandingQuery, SettlementEntryType::CommissionAdjustment)
            - (float) $this->sumByType(clone $outstandingQuery, SettlementEntryType::CommissionPaid),
        );

        return [
            'commission_due' => $commissionDue,
            'commission_paid' => $commissionPaid,
            'commission_adjustments' => $commissionAdjustments,
            'outstanding_commission' => $outstandingCommission,
            'monthly' => $this->monthlyBreakdown($range),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function monthlyBreakdown(SettlementDateRange $range): array
    {
        $query = DB::table('settlement_entries')
            ->selectRaw($this->monthExpression('occurred_at').' as month')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as commission_due',
                [SettlementEntryType::CommissionDue->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE 0 END), 0) as commission_paid',
                [SettlementEntryType::CommissionPaid->value],
            )
            ->groupBy('month')
            ->orderBy('month');

        $this->applyDateRange($query, 'occurred_at', $range);

        /** @var Collection<int, object> $rows */
        $rows = $query->get();

        return $rows->map(fn (object $row): array => [
            'month' => (string) $row->month,
            'commission_due' => $this->formatAmount($row->commission_due ?? 0),
            'commission_paid' => $this->formatAmount($row->commission_paid ?? 0),
        ])->values()->all();
    }

    private function sumByType(Builder $query, SettlementEntryType $type): string
    {
        return $this->formatAmount(
            (clone $query)->where('type', $type->value)->sum('amount'),
        );
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "strftime('%Y-%m', {$column})",
        };
    }
}
