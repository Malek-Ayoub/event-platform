<?php

namespace App\Services\Dashboard\Queries\Admin;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Services\Settlements\Data\SettlementDateRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardAlertsQuery
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(?Carbon $asOf = null): array
    {
        $moment = ($asOf ?? now())->copy();
        $today = new SettlementDateRange(
            from: $moment->copy()->startOfDay(),
            to: $moment->copy()->endOfDay(),
        );

        return [
            $this->outstandingCommissionAlert(),
            $this->eventsStartingTodayAlert($today),
            $this->failedPaymentVerificationsAlert($moment),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outstandingCommissionAlert(): array
    {
        $rows = DB::table('settlement_entries')
            ->select('venue_id')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN amount WHEN type IN (?, ?) THEN -amount ELSE 0 END), 0) as outstanding_commission',
                [
                    SettlementEntryType::CommissionDue->value,
                    SettlementEntryType::CommissionAdjustment->value,
                    SettlementEntryType::CommissionPaid->value,
                ],
            )
            ->groupBy('venue_id')
            ->havingRaw('outstanding_commission > 0')
            ->get();

        $totalOutstanding = '0.00';
        foreach ($rows as $row) {
            $totalOutstanding = bcadd($totalOutstanding, number_format((float) $row->outstanding_commission, 2, '.', ''), 2);
        }

        return [
            'type' => 'outstanding_commission',
            'severity' => 'warning',
            'count' => $rows->count(),
            'amount' => $totalOutstanding,
            'message' => 'Organizers owe platform commission.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventsStartingTodayAlert(SettlementDateRange $today): array
    {
        $count = (int) DB::table('events')
            ->whereNull('deleted_at')
            ->whereBetween('start_datetime', [$today->from, $today->to])
            ->count();

        return [
            'type' => 'events_starting_today',
            'severity' => 'info',
            'count' => $count,
            'message' => 'Events scheduled to start today.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failedPaymentVerificationsAlert(Carbon $asOf): array
    {
        $since = $asOf->copy()->subDay();

        $count = (int) DB::table('payment_transactions')
            ->where('status', PaymentTransactionStatus::Failed->value)
            ->where('updated_at', '>=', $since)
            ->count();

        return [
            'type' => 'failed_payment_verifications',
            'severity' => 'danger',
            'count' => $count,
            'message' => 'Failed payment verifications in the last 24 hours.',
        ];
    }
}
