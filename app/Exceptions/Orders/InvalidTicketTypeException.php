<?php

namespace App\Exceptions\Orders;

use RuntimeException;

class InvalidTicketTypeException extends RuntimeException
{
    public static function forOrder(int $ticketTypeId, int $orderId): self
    {
        return new self(
            "Ticket type {$ticketTypeId} does not belong to the same event as order {$orderId}.",
        );
    }
}
