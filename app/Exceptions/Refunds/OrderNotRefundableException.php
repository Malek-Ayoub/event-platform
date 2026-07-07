<?php

namespace App\Exceptions\Refunds;

use App\Enums\OrdersDomain\OrderStatus;
use RuntimeException;

class OrderNotRefundableException extends RuntimeException
{
    public static function forOrder(int $orderId, OrderStatus $status): self
    {
        return new self(
            "Order {$orderId} is not refundable while in {$status->value} status.",
        );
    }
}
