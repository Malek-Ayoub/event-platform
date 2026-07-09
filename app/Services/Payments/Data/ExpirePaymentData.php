<?php

namespace App\Services\Payments\Data;

use App\Models\User;

/**
 * `PaymentService::expirePayment()` input (Batch 7.6 — Manual Wallet Transfer).
 * `verifying → expired` (terminal — a retry uses a new `PaymentTransaction`).
 */
readonly class ExpirePaymentData
{
    public function __construct(
        public int $paymentTransactionId,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
