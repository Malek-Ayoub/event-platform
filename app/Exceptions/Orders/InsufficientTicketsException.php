<?php

namespace App\Exceptions\Orders;

use RuntimeException;

class InsufficientTicketsException extends RuntimeException
{
    public static function forTicketType(int $ticketTypeId, int $requested, int $available): self
    {
        return new self(
            "Insufficient tickets for ticket type {$ticketTypeId}: requested {$requested}, available {$available}.",
        );
    }
}
