<?php

namespace App\Support\Correlation;

/** Shared `{provider}:{reference}` correlation format for payments and webhooks. */
final class ProviderCorrelation
{
    public static function id(string $provider, string $referenceId): string
    {
        return strtolower(trim($provider)).':'.$referenceId;
    }
}
