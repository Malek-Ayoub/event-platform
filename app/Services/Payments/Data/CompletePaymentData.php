<?php

namespace App\Services\Payments\Data;

use App\Models\User;

readonly class CompletePaymentData
{
    public function __construct(
        public int $paymentTransactionId,
        public ?string $paymentMethod = null,
        public ?string $paymentReference = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
