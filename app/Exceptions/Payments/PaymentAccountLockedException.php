<?php

namespace App\Exceptions\Payments;

use RuntimeException;

final class PaymentAccountLockedException extends RuntimeException
{
    public static function becauseOrdersReferenceAccount(int $paymentAccountId): self
    {
        return new self(
            "Payment account [{$paymentAccountId}] cannot be modified or deleted because existing orders reference it.",
        );
    }

    public static function becauseEventHasOrders(int $eventId): self
    {
        return new self(
            "Payment accounts for event [{$eventId}] cannot be removed because the event already has orders.",
        );
    }
}
