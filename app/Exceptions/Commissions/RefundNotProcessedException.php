<?php

namespace App\Exceptions\Commissions;

use RuntimeException;

class RefundNotProcessedException extends RuntimeException
{
    public static function forRefund(int $refundId): self
    {
        return new self(
            "Refund {$refundId} must be processed before recording a commission adjustment.",
        );
    }
}
