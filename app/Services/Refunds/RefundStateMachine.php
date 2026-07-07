<?php

namespace App\Services\Refunds;

use App\Enums\FinancialDomain\RefundStatus;
use App\Exceptions\Refunds\InvalidRefundStateTransitionException;

/**
 * Allowed transitions (schema-aligned):
 *
 * pending   → processed, failed
 * processed → (terminal)
 * failed    → (terminal — retry uses a new Refund)
 */
class RefundStateMachine
{
    /** @var array<string, list<RefundStatus>> */
    private const ALLOWED = [
        'pending' => [
            RefundStatus::Processed,
            RefundStatus::Failed,
        ],
        'processed' => [],
        'failed' => [],
    ];

    public function assertCanTransition(RefundStatus $from, RefundStatus $to): void
    {
        $allowed = self::ALLOWED[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw InvalidRefundStateTransitionException::between($from, $to);
        }
    }
}
