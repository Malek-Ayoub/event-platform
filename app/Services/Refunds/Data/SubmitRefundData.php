<?php

namespace App\Services\Refunds\Data;

use App\Models\User;

readonly class SubmitRefundData
{
    public function __construct(
        public int $refundId,
        public string $providerRefundId,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
