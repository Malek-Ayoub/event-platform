<?php

namespace App\Services\Reports\Queries;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Services\Reports\Queries\Concerns\AppliesReportDateRange;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Facades\DB;

class PaymentMethodReportQuery
{
    use AppliesReportDateRange;

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(SettlementDateRange $range): array
    {
        $query = DB::table('payment_transactions')
            ->select('provider')
            ->selectRaw('COUNT(*) as transactions_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->whereIn('status', [
                PaymentTransactionStatus::Paid->value,
                PaymentTransactionStatus::Completed->value,
            ])
            ->groupBy('provider')
            ->orderByDesc('total_amount');

        $this->applyDateRange($query, 'updated_at', $range);

        return $query->get()->map(fn (object $row): array => [
            'method' => (string) $row->provider,
            'transactions_count' => (int) $row->transactions_count,
            'total_amount' => $this->formatAmount($row->total_amount ?? 0),
        ])->all();
    }
}
