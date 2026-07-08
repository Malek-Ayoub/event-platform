<?php

namespace App\Exceptions\Commissions;

use RuntimeException;

class AdjustmentExceedsCommissionException extends RuntimeException
{
    public static function forCommission(int $commissionId, string $requested, string $available): self
    {
        return new self(
            "Commission adjustment {$requested} exceeds remaining adjustable amount {$available} for commission {$commissionId}.",
        );
    }
}
