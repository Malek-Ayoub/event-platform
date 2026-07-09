<?php

namespace App\Enums\FinancialDomain;

/**
 * Batch 7.6 amendment (IMPLEMENTATION_ROADMAP.md §7.9.7): the hosted-checkout
 * states (`Pending`, `Completed`, `Refunded`) are legacy/dormant — kept only
 * because the hosted-checkout gateway layer (§7.9.2) is not deleted, per the
 * "dormant, not deleted" decision. `Failed` is shared between both flows
 * (same terminal meaning in each). New Manual Wallet Transfer states
 * (`AwaitingTransfer`, `Verifying`, `Paid`, `Expired`) are additive.
 */
enum PaymentTransactionStatus: string
{
    // Legacy — hosted checkout (dormant, §7.9.2). Do not use in new code.
    case Pending = 'pending';
    case Completed = 'completed';
    case Refunded = 'refunded';

    // Shared terminal state (both legacy and Manual Wallet Transfer flows).
    case Failed = 'failed';

    // Manual Wallet Transfer (current payment model, §7.9).
    case AwaitingTransfer = 'awaiting_transfer';
    case Verifying = 'verifying';
    case Paid = 'paid';
    case Expired = 'expired';
}
