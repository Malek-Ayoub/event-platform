<?php

namespace App\Exceptions\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use RuntimeException;

class OrderNotEligibleForTicketIssuanceException extends RuntimeException
{
    public static function forOrder(int $orderId, OrderStatus $status): self
    {
        return new self(
            "Order {$orderId} is not eligible for ticket issuance (status: {$status->value}).",
        );
    }
}
