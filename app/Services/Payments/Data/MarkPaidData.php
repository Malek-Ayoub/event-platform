<?php

namespace App\Services\Payments\Data;

use App\Models\User;

/**
 * `PaymentService::markPaid()` input (Batch 7.6 — Manual Wallet Transfer).
 * `verifying → paid` + `Order → paid`. Distinct from legacy `CompletePaymentData`
 * (hosted checkout, dormant, §7.9.2).
 */
readonly class MarkPaidData
{
    public function __construct(
        public int $paymentTransactionId,
        public string $providerTransactionId,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
