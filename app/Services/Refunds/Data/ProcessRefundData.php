<?php

namespace App\Services\Refunds\Data;

use App\Models\User;

readonly class ProcessRefundData
{
    public function __construct(
        public int $refundId,
        public ?string $providerRefundId = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
