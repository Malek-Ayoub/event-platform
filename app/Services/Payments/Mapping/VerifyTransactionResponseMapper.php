<?php

namespace App\Services\Payments\Mapping;

use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use App\Enums\Payments\VerificationFailureReason;
use App\Services\Payments\Data\TransactionVerificationResult;

/**
 * Pure mapper (Batch 7.6) — no models, no config, no domain services.
 *
 * Evaluates IMPLEMENTATION_ROADMAP.md §7.9.6 rules #1–#4 (transaction exists,
 * amount matches, currency matches, receiver matches) by comparing the raw
 * gateway response against the expected values. Rules #5–#6 (uniqueness,
 * awaiting_transfer status) require domain state and are evaluated by
 * `PaymentVerificationService` before the gateway is ever called.
 */
final class VerifyTransactionResponseMapper
{
    public function toDomainResult(
        VerifyTransactionResponse $response,
        string $expectedAmount,
        string $expectedCurrency,
        string $expectedReceiverAccount,
    ): TransactionVerificationResult {
        if (! $response->found || $response->providerTransactionId === null) {
            return TransactionVerificationResult::failure(VerificationFailureReason::NotFound);
        }

        if (! $this->amountsMatch($response->amount, $expectedAmount)) {
            return TransactionVerificationResult::failure(VerificationFailureReason::AmountMismatch);
        }

        if (! $this->currenciesMatch($response->currency, $expectedCurrency)) {
            return TransactionVerificationResult::failure(VerificationFailureReason::CurrencyMismatch);
        }

        if (! $this->receiversMatch($response->receiverAccount, $expectedReceiverAccount)) {
            return TransactionVerificationResult::failure(VerificationFailureReason::ReceiverMismatch);
        }

        return TransactionVerificationResult::success($response->providerTransactionId);
    }

    private function amountsMatch(?string $actual, string $expected): bool
    {
        if ($actual === null || $actual === '') {
            return false;
        }

        return bccomp($this->normalizeAmount($actual), $this->normalizeAmount($expected), 2) === 0;
    }

    private function currenciesMatch(?string $actual, string $expected): bool
    {
        if ($actual === null || $actual === '') {
            return false;
        }

        return strtoupper($actual) === strtoupper($expected);
    }

    private function receiversMatch(?string $actual, string $expected): bool
    {
        if ($actual === null || $actual === '') {
            return false;
        }

        return trim($actual) === trim($expected);
    }

    private function normalizeAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
