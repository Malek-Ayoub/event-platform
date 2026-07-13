<?php

namespace App\Services\Settlements\Data;

readonly class SettlementSummaryData
{
    public function __construct(
        public string $grossSales,
        public int $ticketsSold,
        public string $commissionDue,
        public string $commissionPaid,
        public string $commissionAdjustments,
        public string $commissionOutstanding,
        public string $refunds,
        public string $netSales,
        public string $currency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'gross_sales' => $this->grossSales,
            'tickets_sold' => $this->ticketsSold,
            'commission_due' => $this->commissionDue,
            'commission_paid' => $this->commissionPaid,
            'commission_adjustments' => $this->commissionAdjustments,
            'commission_outstanding' => $this->commissionOutstanding,
            'refunds' => $this->refunds,
            'net_sales' => $this->netSales,
            'currency' => $this->currency,
        ];
    }
}
