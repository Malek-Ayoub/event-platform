<?php

namespace App\Services\Payments\Data;

use App\Models\User;

/**
 * `PaymentVerificationService::verify()` input (Batch 7.6 — API wiring lands
 * in Batch 7.7; this DTO is the stable boundary the future controller maps into).
 */
readonly class VerifyTransactionData
{
    public function __construct(
        public int $paymentTransactionId,
        public string $transactionNumber,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
