<?php

namespace App\Services\Outbox;

interface SupportsOutboxEventType
{
    public function eventType(): string;
}
