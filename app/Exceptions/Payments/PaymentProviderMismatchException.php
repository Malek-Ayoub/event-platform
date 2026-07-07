<?php

namespace App\Exceptions\Payments;

use RuntimeException;

class PaymentProviderMismatchException extends RuntimeException
{
    public static function forProviderTransaction(
        string $provider,
        string $providerTransactionId,
        int $expectedOrderId,
        int $actualOrderId,
    ): self {
        return new self(
            "Provider transaction {$provider}/{$providerTransactionId} is already linked to order {$actualOrderId}, not {$expectedOrderId}.",
        );
    }
}
