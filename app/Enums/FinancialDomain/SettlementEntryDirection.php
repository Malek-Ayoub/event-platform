<?php

namespace App\Enums\FinancialDomain;

/**
 * Platform commission receivable ledger direction (Phase 8.5).
 *
 * Credit increases outstanding commission owed by the organizer to the platform.
 * Debit decreases outstanding commission (adjustments after refunds, manual payments).
 */
enum SettlementEntryDirection: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
