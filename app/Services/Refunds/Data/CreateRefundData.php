<?php

namespace App\Services\Refunds\Data;

use App\Models\User;

readonly class CreateRefundData
{
    public function __construct(
        public int $orderId,
        public string $amount,
        public ?int $paymentTransactionId = null,
        public ?string $reason = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
