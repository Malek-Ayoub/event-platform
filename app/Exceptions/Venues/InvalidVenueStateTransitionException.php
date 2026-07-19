<?php

namespace App\Exceptions\Venues;

use RuntimeException;

class InvalidVenueStateTransitionException extends RuntimeException
{
    public static function alreadySuspended(): self
    {
        return new self('Venue is already suspended.');
    }

    public static function alreadyActive(): self
    {
        return new self('Venue is already active.');
    }

    public static function cannotSuspendFrom(string $status): self
    {
        if ($status === 'suspended') {
            return self::alreadySuspended();
        }

        return new self("Venue cannot be suspended from [{$status}] status.");
    }

    public static function cannotActivateFrom(string $status): self
    {
        if ($status === 'active') {
            return self::alreadyActive();
        }

        return new self("Venue cannot be activated from [{$status}] status.");
    }
}
