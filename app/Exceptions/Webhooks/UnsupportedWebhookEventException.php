<?php

namespace App\Exceptions\Webhooks;

use RuntimeException;

class UnsupportedWebhookEventException extends RuntimeException
{
    public static function forEventType(string $eventType): self
    {
        return new self("Unsupported webhook event type [{$eventType}].");
    }
}
