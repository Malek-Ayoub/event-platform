<?php

namespace App\Services\Payments\Data;

use App\Models\User;

readonly class FailPaymentData
{
    public function __construct(
        public int $paymentTransactionId,
        public ?string $reason = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
