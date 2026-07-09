<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Thrown when a customer-submitted transaction number is already linked to
 * another payment platform-wide (IMPLEMENTATION_ROADMAP.md §7.9.6.1).
 */
final class DuplicateTransactionNumberException extends RuntimeException
{
    public static function forTransactionNumber(string $transactionNumber): self
    {
        return new self(
            "Transaction number [{$transactionNumber}] has already been used for another payment.",
        );
    }
}
