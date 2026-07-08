<?php

namespace App\Exceptions\Webhooks;

use RuntimeException;

class DuplicateWebhookException extends RuntimeException
{
    public static function forEvent(string $provider, string $providerEventId): self
    {
        return new self("Webhook event [{$provider}:{$providerEventId}] was already processed.");
    }
}
