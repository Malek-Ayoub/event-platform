<?php

namespace App\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Exceptions\Payments\InvalidPaymentStateTransitionException;

/**
 * Allowed transitions (schema-aligned):
 *
 * Legacy — hosted checkout (dormant, §7.9.2 — do not extend further):
 * pending   → completed, failed
 * completed → refunded
 * failed    → (terminal — retry uses a new PaymentTransaction)
 * refunded  → (terminal)
 *
 * Manual Wallet Transfer (current model, §7.9.7 — Batch 7.6):
 * awaiting_transfer → verifying
 * verifying         → paid, failed, expired
 * paid              → (terminal — refunds tracked independently via `refunds.status`, §7.9.2)
 * expired           → (terminal — retry uses a new PaymentTransaction, mirrors legacy `failed`)
 *
 * `failed` is intentionally shared between both flows (identical terminal meaning);
 * no transition targets it from `awaiting_transfer` directly — verification always
 * passes through `verifying` first, so failures are always `verifying → failed`.
 */
class PaymentTransactionStateMachine
{
    /** @var array<string, list<PaymentTransactionStatus>> */
    private const ALLOWED = [
        // Legacy — hosted checkout (dormant).
        'pending' => [
            PaymentTransactionStatus::Completed,
            PaymentTransactionStatus::Failed,
        ],
        'completed' => [
            PaymentTransactionStatus::Refunded,
        ],
        'refunded' => [],

        // Manual Wallet Transfer (current model).
        'awaiting_transfer' => [
            PaymentTransactionStatus::Verifying,
            PaymentTransactionStatus::Expired,
        ],
        'verifying' => [
            PaymentTransactionStatus::Paid,
            PaymentTransactionStatus::Failed,
            PaymentTransactionStatus::Expired,
        ],
        'paid' => [],
        'expired' => [],

        // Shared terminal state.
        'failed' => [],
    ];

    public function assertCanTransition(
        PaymentTransactionStatus $from,
        PaymentTransactionStatus $to,
    ): void {
        $allowed = self::ALLOWED[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw InvalidPaymentStateTransitionException::between($from, $to);
        }
    }

    public function canTransition(
        PaymentTransactionStatus $from,
        PaymentTransactionStatus $to,
    ): bool {
        try {
            $this->assertCanTransition($from, $to);

            return true;
        } catch (InvalidPaymentStateTransitionException) {
            return false;
        }
    }
}
