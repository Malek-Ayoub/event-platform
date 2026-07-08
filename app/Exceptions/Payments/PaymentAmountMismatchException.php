<?php

namespace App\Exceptions\Payments;

use RuntimeException;

class PaymentAmountMismatchException extends RuntimeException
{
    public static function forOrder(int $orderId, string $submittedAmount, string $orderTotal): self
    {
        return new self(
            "Payment amount {$submittedAmount} does not match order {$orderId} total {$orderTotal}.",
        );
    }
}
