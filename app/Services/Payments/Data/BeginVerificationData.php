<?php

namespace App\Services\Payments\Data;

use App\Models\User;

/**
 * `PaymentService::beginVerification()` input (Batch 7.6 — Manual Wallet Transfer).
 * Transitions `awaiting_transfer → verifying` and records the submitted
 * `transaction_number` (idempotent no-op if already `verifying`, to allow retries
 * after a transient gateway error).
 */
readonly class BeginVerificationData
{
    public function __construct(
        public int $paymentTransactionId,
        public string $transactionNumber,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
