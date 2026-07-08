<?php

namespace App\Exceptions\Webhooks;

use RuntimeException;

class WebhookVerificationException extends RuntimeException
{
    public static function forProvider(string $provider, string $reason): self
    {
        return new self("Webhook signature verification failed for provider [{$provider}]: {$reason}");
    }
}
