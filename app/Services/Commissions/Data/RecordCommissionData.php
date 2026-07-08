<?php

namespace App\Services\Commissions\Data;

use App\Models\User;

readonly class RecordCommissionData
{
    public function __construct(
        public int $orderId,
        public ?int $paymentTransactionId = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
