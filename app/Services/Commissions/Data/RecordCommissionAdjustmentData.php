<?php

namespace App\Services\Commissions\Data;

use App\Models\User;

readonly class RecordCommissionAdjustmentData
{
    public function __construct(
        public int $refundId,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
