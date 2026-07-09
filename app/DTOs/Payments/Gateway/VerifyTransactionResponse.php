<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;
use App\Enums\Payments\GatewayOutcome;

/**
 * Raw gateway-layer transaction-lookup response (Batch 7.6 — Manual Wallet
 * Transfer). `outcome` reflects the technical outcome of the lookup call
 * itself (network/provider errors) — `found` reflects whether the provider
 * located a transaction for the submitted number. Business validation
 * (amount/currency/receiver matching) happens in the ACL mapper, not here.
 */
readonly class VerifyTransactionResponse extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public GatewayOutcome $outcome,
        public bool $found,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $receiverAccount = null,
        public ?string $providerTransactionId = null,
        public ?string $rawStatus = null,
        public array $providerMetadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            outcome: isset($data['outcome'])
                ? GatewayOutcome::from((string) $data['outcome'])
                : GatewayOutcome::Unknown,
            found: (bool) ($data['found'] ?? false),
            amount: isset($data['amount']) ? (string) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            receiverAccount: isset($data['receiver_account']) ? (string) $data['receiver_account'] : null,
            providerTransactionId: isset($data['provider_transaction_id']) ? (string) $data['provider_transaction_id'] : null,
            rawStatus: isset($data['raw_status']) ? (string) $data['raw_status'] : null,
            providerMetadata: isset($data['provider_metadata']) && is_array($data['provider_metadata'])
                ? $data['provider_metadata']
                : [],
        );
    }

    public function toArray(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'found' => $this->found,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'receiver_account' => $this->receiverAccount,
            'provider_transaction_id' => $this->providerTransactionId,
            'raw_status' => $this->rawStatus,
            'provider_metadata' => $this->providerMetadata,
        ];
    }
}
