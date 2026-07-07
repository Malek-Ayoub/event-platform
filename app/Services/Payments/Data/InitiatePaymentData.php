<?php

namespace App\Services\Payments\Data;

use App\Models\User;

readonly class InitiatePaymentData
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
        public ?array $payload = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
