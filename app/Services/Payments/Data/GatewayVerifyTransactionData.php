<?php

namespace App\Services\Payments\Data;

use App\DTOs\Payments\Gateway\GatewayPaymentAccount;

/**
 * ACL-level input to `PaymentGatewayService::verifyTransaction()` (Batch 7.6).
 */
readonly class GatewayVerifyTransactionData
{
    public function __construct(
        public string $provider,
        public string $transactionNumber,
        public string $expectedAmount,
        public string $expectedCurrency,
        public GatewayPaymentAccount $paymentAccount,
    ) {}
}
