<?php

namespace App\Services\Payments\Data;

use App\Models\User;

readonly class GatewayRefundData
{
    public function __construct(
        public int $refundId,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
