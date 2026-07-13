<?php

namespace App\Services\Commissions\Data;

use App\Models\CommissionPayment;
use App\Models\SettlementEntry;

readonly class RecordCommissionPaymentResult
{
    public function __construct(
        public CommissionPayment $payment,
        public SettlementEntry $settlementEntry,
    ) {}
}
