<?php

namespace App\Exceptions\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use RuntimeException;

class InvalidPaymentStateTransitionException extends RuntimeException
{
    public static function between(
        PaymentTransactionStatus $from,
        PaymentTransactionStatus $to,
    ): self {
        return new self(
            "Invalid payment transition from {$from->value} to {$to->value}.",
        );
    }
}
