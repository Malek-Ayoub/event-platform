<?php

namespace App\Enums\Payments;

/**
 * Verification rejection reasons (IMPLEMENTATION_ROADMAP.md §7.9.6 — locked
 * business rules). A transaction is valid only if none of these apply.
 */
enum VerificationFailureReason: string
{
    case NotFound = 'not_found';
    case AmountMismatch = 'amount_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
    case ReceiverMismatch = 'receiver_mismatch';
    case DuplicateTransactionNumber = 'duplicate_transaction_number';
    case NotAwaitingTransfer = 'not_awaiting_transfer';
    case Expired = 'expired';
}
