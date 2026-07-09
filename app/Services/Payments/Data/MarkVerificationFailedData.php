<?php

namespace App\Services\Payments\Data;

use App\Enums\Payments\VerificationFailureReason;
use App\Models\User;

/**
 * `PaymentService::markVerificationFailed()` input (Batch 7.6 — Manual Wallet
 * Transfer). `verifying → failed` (terminal — a retry uses a new
 * `PaymentTransaction`, mirroring the legacy hosted-checkout convention).
 */
readonly class MarkVerificationFailedData
{
    public function __construct(
        public int $paymentTransactionId,
        public VerificationFailureReason $reason,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
