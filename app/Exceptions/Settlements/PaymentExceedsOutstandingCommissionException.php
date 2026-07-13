<?php

namespace App\Exceptions\Settlements;

use RuntimeException;

class PaymentExceedsOutstandingCommissionException extends RuntimeException
{
    public static function forVenue(int $venueId, string $requested, string $outstanding): self
    {
        return new self(
            "Commission payment {$requested} exceeds outstanding commission {$outstanding} for venue {$venueId}.",
        );
    }
}
