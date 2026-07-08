<?php

namespace App\Exceptions\Commissions;

use App\Enums\OrdersDomain\OrderStatus;
use RuntimeException;

class OrderNotEligibleForCommissionException extends RuntimeException
{
    public static function forOrder(int $orderId, OrderStatus $status): self
    {
        return new self(
            "Order {$orderId} is not eligible for commission while in {$status->value} status.",
        );
    }
}
