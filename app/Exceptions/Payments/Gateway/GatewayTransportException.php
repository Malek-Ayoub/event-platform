<?php

namespace App\Exceptions\Payments\Gateway;

use App\Enums\Payments\GatewayOutcome;
use RuntimeException;
use Throwable;

final class GatewayTransportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly GatewayOutcome $outcome,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
