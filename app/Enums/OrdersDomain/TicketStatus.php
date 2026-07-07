<?php

namespace App\Enums\OrdersDomain;

enum TicketStatus: string
{
    case Valid = 'valid';
    case Used = 'used';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
