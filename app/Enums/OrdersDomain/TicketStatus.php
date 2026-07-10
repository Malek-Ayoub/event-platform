<?php

namespace App\Enums\OrdersDomain;

enum TicketStatus: string
{
    case Issued = 'issued';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Invalidated = 'invalidated';
}
