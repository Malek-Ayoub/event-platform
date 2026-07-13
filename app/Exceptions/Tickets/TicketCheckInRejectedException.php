<?php

namespace App\Exceptions\Tickets;

use App\Enums\OrdersDomain\TicketStatus;
use RuntimeException;

class TicketCheckInRejectedException extends RuntimeException
{
    public static function alreadyCheckedIn(): self
    {
        return new self('Ticket has already been checked in.');
    }

    public static function refunded(): self
    {
        return new self('Ticket has been refunded.');
    }

    public static function cancelled(): self
    {
        return new self('Ticket has been cancelled.');
    }

    public static function invalidated(): self
    {
        return new self('Ticket has been invalidated.');
    }

    public static function eventEnded(): self
    {
        return new self('The event for this ticket has already ended.');
    }

    public static function forStatus(TicketStatus $status): self
    {
        return match ($status) {
            TicketStatus::CheckedIn => self::alreadyCheckedIn(),
            TicketStatus::Refunded => self::refunded(),
            TicketStatus::Cancelled => self::cancelled(),
            TicketStatus::Invalidated => self::invalidated(),
            TicketStatus::Issued => new self('Ticket is not eligible for check-in.'),
        };
    }
}
