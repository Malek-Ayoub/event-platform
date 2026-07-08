<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

readonly class RefundResponse extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    public function __construct(
        public string $providerRefundId,
        public string $status,
        public array $providerMetadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerRefundId: (string) $data['provider_refund_id'],
            status: (string) $data['status'],
            providerMetadata: isset($data['provider_metadata']) && is_array($data['provider_metadata'])
                ? $data['provider_metadata']
                : [],
        );
    }

    public function toArray(): array
    {
        return [
            'provider_refund_id' => $this->providerRefundId,
            'status' => $this->status,
            'provider_metadata' => $this->providerMetadata,
        ];
    }
}
