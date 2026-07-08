<?php

namespace App\Exceptions\Payments\Gateway;

use RuntimeException;

class UnknownPaymentProviderException extends RuntimeException
{
    public static function forProvider(string $provider, string $contract = 'gateway'): self
    {
        return new self("No payment {$contract} registered for provider [{$provider}].");
    }
}
