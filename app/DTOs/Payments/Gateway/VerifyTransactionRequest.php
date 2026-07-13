<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;
use App\Enums\Payments\PaymentWalletProvider;

/**
 * Gateway-layer transaction-lookup request (Batch 7.6 — Manual Wallet Transfer).
 */
readonly class VerifyTransactionRequest extends BaseDTO
{
    public function __construct(
        public string $transactionNumber,
        public string $expectedAmount,
        public string $expectedCurrency,
        public GatewayPaymentAccount $paymentAccount,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $accountData */
        $accountData = (array) ($data['payment_account'] ?? []);

        return new self(
            transactionNumber: (string) $data['transaction_number'],
            expectedAmount: (string) $data['expected_amount'],
            expectedCurrency: (string) $data['expected_currency'],
            paymentAccount: new GatewayPaymentAccount(
                provider: PaymentWalletProvider::from((string) $accountData['provider']),
                accountIdentifier: (string) $accountData['account_identifier'],
                cashCode: isset($accountData['cash_code']) ? (string) $accountData['cash_code'] : null,
                currency: isset($accountData['currency']) ? (string) $accountData['currency'] : null,
                displayName: isset($accountData['display_name']) ? (string) $accountData['display_name'] : null,
            ),
        );
    }

    public function toArray(): array
    {
        return [
            'transaction_number' => $this->transactionNumber,
            'expected_amount' => $this->expectedAmount,
            'expected_currency' => $this->expectedCurrency,
            'payment_account' => [
                'provider' => $this->paymentAccount->provider->value,
                'account_identifier' => $this->paymentAccount->accountIdentifier,
                'cash_code' => $this->paymentAccount->cashCode,
                'currency' => $this->paymentAccount->currency,
                'display_name' => $this->paymentAccount->displayName,
            ],
        ];
    }
}
