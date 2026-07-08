<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

readonly class RefundRequest extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $providerTransactionId,
        public string $amount,
        public string $currency,
        public ?string $reason = null,
        public ?string $providerRefundId = null,
        public array $metadata = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            providerTransactionId: (string) $data['provider_transaction_id'],
            amount: (string) $data['amount'],
            currency: (string) ($data['currency'] ?? 'USD'),
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
            providerRefundId: isset($data['provider_refund_id']) ? (string) $data['provider_refund_id'] : null,
            metadata: isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        );
    }

    public function toArray(): array
    {
        return [
            'provider_transaction_id' => $this->providerTransactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reason' => $this->reason,
            'provider_refund_id' => $this->providerRefundId,
            'metadata' => $this->metadata,
        ];
    }
}
