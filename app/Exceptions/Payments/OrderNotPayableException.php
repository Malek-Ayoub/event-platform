<?php

namespace App\Exceptions\Payments;

use App\Enums\OrdersDomain\OrderStatus;
use RuntimeException;

class OrderNotPayableException extends RuntimeException
{
    public static function forOrder(int $orderId, OrderStatus $status): self
    {
        return new self(
            "Order {$orderId} is not payable while in {$status->value} status.",
        );
    }
}
