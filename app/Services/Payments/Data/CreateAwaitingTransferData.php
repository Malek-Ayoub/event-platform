<?php

namespace App\Services\Payments\Data;

use App\Models\User;
use DateTimeInterface;

/**
 * `PaymentService::createAwaitingTransfer()` input (Batch 7.6 — Manual Wallet
 * Transfer). Distinct from legacy `InitiatePaymentData` (hosted checkout,
 * dormant, §7.9.2) — no `providerTransactionId` exists yet at this stage.
 */
readonly class CreateAwaitingTransferData
{
    public function __construct(
        public int $orderId,
        public string $provider,
        public string $amount,
        public string $currency,
        public DateTimeInterface $expiresAt,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
