<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

/**
 * Gateway-layer transaction-lookup request (Batch 7.6 — Manual Wallet Transfer).
 */
readonly class VerifyTransactionRequest extends BaseDTO
{
    public function __construct(
        public string $transactionNumber,
        public string $expectedAmount,
        public string $expectedCurrency,
        public string $merchantAccount,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionNumber: (string) $data['transaction_number'],
            expectedAmount: (string) $data['expected_amount'],
            expectedCurrency: (string) $data['expected_currency'],
            merchantAccount: (string) $data['merchant_account'],
        );
    }

    public function toArray(): array
    {
        return [
            'transaction_number' => $this->transactionNumber,
            'expected_amount' => $this->expectedAmount,
            'expected_currency' => $this->expectedCurrency,
            'merchant_account' => $this->merchantAccount,
        ];
    }
}
