<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

/** Gateway-layer initiate payment request (not the HTTP `InitiatePaymentDTO`). */
readonly class InitiatePaymentRequest extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $orderId,
        public string $amount,
        public string $currency,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderId: (int) $data['order_id'],
            amount: (string) $data['amount'],
            currency: (string) ($data['currency'] ?? 'USD'),
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
        ];
    }
}
