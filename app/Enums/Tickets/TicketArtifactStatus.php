<?php

namespace App\Enums\Tickets;

enum TicketArtifactStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleted = 'deleted';
}
