<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

readonly class InitiatePaymentResponse extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public string $providerTransactionId,
        public string $status,
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
            'redirect_url' => $this->redirectUrl,
            'provider_metadata' => $this->providerMetadata,
        ];
    }
}
