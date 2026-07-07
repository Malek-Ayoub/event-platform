<?php

namespace App\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Exceptions\Payments\InvalidPaymentStateTransitionException;

/**
 * Allowed transitions (schema-aligned):
 *
 * pending   → completed, failed
 * completed → refunded
 * failed    → (terminal — retry uses a new PaymentTransaction)
 * refunded  → (terminal)
 */
class PaymentTransactionStateMachine
{
    /** @var array<string, list<PaymentTransactionStatus>> */
    private const ALLOWED = [
        'pending' => [
            PaymentTransactionStatus::Completed,
            PaymentTransactionStatus::Failed,
        ],
        'completed' => [
            PaymentTransactionStatus::Refunded,
        ],
        'failed' => [],
        'refunded' => [],
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
