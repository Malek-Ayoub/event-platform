<?php

namespace App\DTOs\Payments;

use App\DTOs\BaseDTO;

readonly class InitiatePaymentDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public int $orderId,
        public string $provider,
        public string $providerTransactionId,
        public string $amount,
        public string $currency,
        public ?array $payload,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderId: (int) $data['order_id'],
            provider: (string) $data['provider'],
            providerTransactionId: (string) $data['provider_transaction_id'],
            amount: (string) $data['amount'],
            currency: isset($data['currency']) ? (string) $data['currency'] : 'USD',
            payload: isset($data['payload']) ? $data['payload'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'provider' => $this->provider,
            'provider_transaction_id' => $this->providerTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payload' => $this->payload,
        ];
    }
}
