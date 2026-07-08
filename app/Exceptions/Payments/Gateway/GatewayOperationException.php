<?php

namespace App\Exceptions\Payments\Gateway;

use App\Enums\Payments\GatewayOutcome;
use RuntimeException;

final class GatewayOperationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly GatewayOutcome $outcome,
        public readonly string $operation,
    ) {
        parent::__construct($message);
    }

    public static function forInitiate(string $provider, GatewayOutcome $outcome, string $reason): self
    {
        return new self(
            message: "Payment initiation failed for provider [{$provider}]: {$reason}",
            provider: $provider,
            outcome: $outcome,
            operation: 'initiate',
        );
    }

    public static function forRefund(string $provider, GatewayOutcome $outcome, string $reason): self
    {
        return new self(
            message: "Refund request failed for provider [{$provider}]: {$reason}",
            provider: $provider,
            outcome: $outcome,
            operation: 'refund',
        );
    }
}
