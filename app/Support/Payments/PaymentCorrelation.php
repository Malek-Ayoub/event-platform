<?php

namespace App\Support\Payments;

use App\Support\Correlation\ProviderCorrelation;

final class PaymentCorrelation
{
    public static function forProviderTransaction(string $provider, string $providerTransactionId): string
    {
        return ProviderCorrelation::id($provider, $providerTransactionId);
    }
}
