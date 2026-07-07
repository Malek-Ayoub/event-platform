<?php

namespace App\Exceptions\Refunds;

use RuntimeException;

class RefundAmountExceedsOrderException extends RuntimeException
{
    public static function forOrder(int $orderId, string $requested, string $available): self
    {
        return new self(
            "Refund amount {$requested} exceeds available refundable amount {$available} for order {$orderId}.",
        );
    }
}
