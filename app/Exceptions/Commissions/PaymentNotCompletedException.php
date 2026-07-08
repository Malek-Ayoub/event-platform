<?php

namespace App\Exceptions\Commissions;

use RuntimeException;

class PaymentNotCompletedException extends RuntimeException
{
    public static function forOrder(int $orderId): self
    {
        return new self(
            "Order {$orderId} has no completed payment transaction.",
        );
    }

    public static function forPaymentTransaction(int $paymentTransactionId): self
    {
        return new self(
            "Payment transaction {$paymentTransactionId} is not completed.",
        );
    }
}
