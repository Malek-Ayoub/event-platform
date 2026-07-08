<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;
use App\Enums\Payments\GatewayOutcome;

readonly class InitiatePaymentResponse extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public string $providerTransactionId,
        public string $status,
        public GatewayOutcome $outcome,
        public ?string $redirectUrl = null,
        public array $providerMetadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerTransactionId: (string) $data['provider_transaction_id'],
            status: (string) $data['status'],
            outcome: isset($data['outcome'])
                ? GatewayOutcome::from((string) $data['outcome'])
                : GatewayOutcome::Unknown,
            redirectUrl: isset($data['redirect_url']) ? (string) $data['redirect_url'] : null,
            providerMetadata: isset($data['provider_metadata']) && is_array($data['provider_metadata'])
                ? $data['provider_metadata']
                : [],
        );
    }

    public function toArray(): array
    {
        return [
            'provider_transaction_id' => $this->providerTransactionId,
            'status' => $this->status,
            'outcome' => $this->outcome->value,
            'redirect_url' => $this->redirectUrl,
            'provider_metadata' => $this->providerMetadata,
        ];
    }
}
