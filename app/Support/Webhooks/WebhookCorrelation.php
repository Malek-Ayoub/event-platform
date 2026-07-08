<?php

namespace App\Support\Webhooks;

final class WebhookCorrelation
{
    public static function id(string $provider, string $providerEventId): string
    {
        return strtolower(trim($provider)).':'.$providerEventId;
    }
}
