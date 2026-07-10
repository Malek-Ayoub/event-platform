<?php

namespace App\Support\Orders;

use App\Models\Ticket;

/**
 * QR payloads contain only the opaque lookup token — never id, serial, or ticket_number.
 */
final class TicketQrPayload
{
    public static function forTicket(Ticket $ticket): string
    {
        return $ticket->qr_token;
    }
}
