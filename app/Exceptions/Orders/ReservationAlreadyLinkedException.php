<?php

namespace App\Exceptions\Orders;

use RuntimeException;

class ReservationAlreadyLinkedException extends RuntimeException
{
    public static function forReservation(int $reservationId, int $existingOrderId): self
    {
        return new self(
            "Reservation {$reservationId} is already linked to order {$existingOrderId}.",
        );
    }
}
