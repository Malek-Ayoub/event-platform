<?php

namespace App\Services\Payments\Data;

/**
 * ACL-level input to `PaymentGatewayService::verifyTransaction()` (Batch 7.6).
 * Pure primitives only — no Eloquent models cross this boundary.
 */
readonly class GatewayVerifyTransactionData
{
    public function __construct(
        public string $provider,
        public string $transactionNumber,
        public string $expectedAmount,
        public string $expectedCurrency,
        public string $merchantAccount,
    ) {}
}
