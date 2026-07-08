<?php

namespace App\Domain\Correlation;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;

class CorrelationContext implements CorrelationContextInterface
{
    private ?string $correlationId = null;

    public function bind(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function isBound(): bool
    {
        return $this->correlationId !== null;
    }

    public function get(): ?string
    {
        return $this->correlationId;
    }

    public function clear(): void
    {
        $this->correlationId = null;
    }
}
