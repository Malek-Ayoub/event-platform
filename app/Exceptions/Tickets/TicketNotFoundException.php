<?php

namespace App\Exceptions\Tickets;

use RuntimeException;

class TicketNotFoundException extends RuntimeException
{
    public static function forQrToken(string $qrToken): self
    {
        return new self('Ticket not found for the provided QR token.');
    }
}
