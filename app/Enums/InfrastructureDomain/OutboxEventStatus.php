<?php

namespace App\Enums\InfrastructureDomain;

enum OutboxEventStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';
}
