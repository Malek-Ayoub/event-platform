<?php

namespace App\Enums\InfrastructureDomain;

enum WebhookLogStatus: string
{
    case Received = 'received';
    case Verified = 'verified';
    case Failed = 'failed';
    case Processed = 'processed';
}
