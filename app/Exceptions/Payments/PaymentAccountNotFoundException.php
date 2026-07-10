<?php

namespace App\Exceptions\Payments;

use RuntimeException;

final class PaymentAccountNotFoundException extends RuntimeException
{
    public static function forEvent(int $eventId): self
    {
        return new self("No active default payment account configured for event [{$eventId}].");
    }

    public static function forPayment(int $paymentTransactionId): self
    {
        return new self("No payment account could be resolved for payment transaction [{$paymentTransactionId}].");
    }
}
