<?php

namespace App\Exceptions\Settlements;

use RuntimeException;

class NoOutstandingCommissionException extends RuntimeException
{
    public static function forVenue(int $venueId): self
    {
        return new self("Venue {$venueId} has no outstanding commission balance to record a payment against.");
    }
}
