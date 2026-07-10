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

    public static function forRefund(string $provider, GatewayOutcome $outcome, string $reason): self
    {
        return new self(
            message: "Refund request failed for provider [{$provider}]: {$reason}",
            provider: $provider,
            outcome: $outcome,
            operation: 'refund',
        );
    }

    /**
     * Batch 7.6 — Manual Wallet Transfer. Thrown only for technical lookup
     * failures (network/provider/timeout) — a business "transaction not found"
     * result is not an exception, see `TransactionVerificationResult` (§7.9.6).
     */
    public static function forVerify(string $provider, GatewayOutcome $outcome, string $reason): self
    {
        return new self(
            message: "Transaction verification failed for provider [{$provider}]: {$reason}",
            provider: $provider,
            outcome: $outcome,
            operation: 'verify',
        );
    }
}
