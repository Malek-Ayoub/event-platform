<?php

namespace App\Enums\FinancialDomain;

enum SettlementEntryType: string
{
    case CommissionDue = 'commission_due';
    case CommissionAdjustment = 'commission_adjustment';
    case CommissionPaid = 'commission_paid';
}
