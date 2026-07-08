<?php

namespace App\Services\Payments\Data;

use App\Models\User;

readonly class GatewayInitiatePaymentData
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
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
