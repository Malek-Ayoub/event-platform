<?php

namespace App\Services\Payments\Data;

use App\Enums\Payments\VerificationFailureReason;

/**
 * Domain-level output of `PaymentGatewayService::verifyTransaction()` (Batch 7.6).
 * `matched = true` iff every rule in IMPLEMENTATION_ROADMAP.md §7.9.6 that the
 * ACL can evaluate from the raw gateway response holds (transaction found,
 * amount/currency/receiver match). Pre-flight rules that require domain state
 * (uniqueness, awaiting_transfer status, expiry) are evaluated by
 * `PaymentVerificationService` before the gateway is ever called.
 */
readonly class TransactionVerificationResult
{
    public function __construct(
        public bool $matched,
        public ?VerificationFailureReason $reason = null,
        public ?string $providerTransactionId = null,
    ) {}

    public static function success(string $providerTransactionId): self
    {
        return new self(matched: true, reason: null, providerTransactionId: $providerTransactionId);
    }

    public static function failure(VerificationFailureReason $reason): self
    {
        return new self(matched: false, reason: $reason);
    }
}
