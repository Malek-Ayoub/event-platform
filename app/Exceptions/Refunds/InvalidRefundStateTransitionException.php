<?php

namespace App\Exceptions\Refunds;

use App\Enums\FinancialDomain\RefundStatus;
use RuntimeException;

class InvalidRefundStateTransitionException extends RuntimeException
{
    public static function between(RefundStatus $from, RefundStatus $to): self
    {
        return new self(
            "Invalid refund transition from {$from->value} to {$to->value}.",
        );
    }
}
