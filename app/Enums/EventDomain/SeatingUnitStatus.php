<?php

namespace App\Enums\EventDomain;

enum SeatingUnitStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Unavailable = 'unavailable';
}
