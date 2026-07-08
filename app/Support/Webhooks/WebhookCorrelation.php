<?php

namespace App\Support\Webhooks;

use App\Support\Correlation\ProviderCorrelation;

final class WebhookCorrelation
{
    public static function id(string $provider, string $providerEventId): string
    {
        return ProviderCorrelation::id($provider, $providerEventId);
    }
}
