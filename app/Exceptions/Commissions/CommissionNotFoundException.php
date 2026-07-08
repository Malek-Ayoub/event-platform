<?php

namespace App\Exceptions\Commissions;

use RuntimeException;

class CommissionNotFoundException extends RuntimeException
{
    public static function forOrder(int $orderId): self
    {
        return new self(
            "No commission exists for order {$orderId}.",
        );
    }
}
