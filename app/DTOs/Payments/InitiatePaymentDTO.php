<?php

namespace App\DTOs\Payments;

use App\DTOs\BaseDTO;

readonly class InitiatePaymentDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $orderId,
        public string $provider,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderId: (int) $data['order_id'],
            provider: (string) $data['provider'],
            amount: isset($data['amount']) ? (string) $data['amount'] : null,
            currency: isset($data['currency']) ? (string) $data['currency'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'order_id' => $this->orderId,
            'provider' => $this->provider,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
