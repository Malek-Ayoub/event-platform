<?php

namespace App\Exceptions\Events;

use App\Enums\EventDomain\EventStatus;
use RuntimeException;

class InvalidEventStateTransitionException extends RuntimeException
{
    public static function between(EventStatus $from, EventStatus $to): self
    {
        return new self(
            "Invalid event status transition from [{$from->value}] to [{$to->value}].",
        );
    }

    public static function archiveFrom(EventStatus $status): self
    {
        return new self(
            "Event cannot be archived from [{$status->value}] status.",
        );
    }
}
