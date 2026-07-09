<?php

namespace App\Services\Payments\Mapping;

use App\DTOs\Payments\Gateway\VerifyTransactionRequest;

/**
 * Pure mapper (Batch 7.6) — no models, no config, no domain services.
 */
final class VerifyTransactionRequestMapper
{
    public function toGatewayRequest(
        string $transactionNumber,
        string $expectedAmount,
        string $expectedCurrency,
        string $merchantAccount,
    ): VerifyTransactionRequest {
        return new VerifyTransactionRequest(
            transactionNumber: $transactionNumber,
            expectedAmount: $expectedAmount,
            expectedCurrency: $expectedCurrency,
            merchantAccount: $merchantAccount,
        );
    }
}
