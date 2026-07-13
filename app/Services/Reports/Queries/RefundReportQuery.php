<?php

namespace App\Services\Reports\Queries;

use App\Enums\FinancialDomain\RefundStatus;
use App\Services\Reports\Queries\Concerns\AppliesReportDateRange;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Facades\DB;

class RefundReportQuery
{
    use AppliesReportDateRange;

    /**
     * @return array<string, mixed>
     */
    public function execute(SettlementDateRange $range, int $ordersCount, string $grossRevenue): array
    {
        $query = DB::table('refunds')
            ->where('status', RefundStatus::Processed->value);

        $this->applyDateRange($query, 'processed_at', $range);

        $refundsCount = (int) (clone $query)->count();
        $refundedAmount = $this->formatAmount((clone $query)->sum('amount'));

        return [
            'refunds_count' => $refundsCount,
            'refunded_amount' => $refundedAmount,
            'refund_rate' => $this->percentageRate((float) $refundedAmount, (float) $grossRevenue),
        ];
    }
}
