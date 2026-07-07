<?php

namespace App\Enums\EventDomain;

enum ReservationStatus: string
{
    case Hold = 'hold';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
