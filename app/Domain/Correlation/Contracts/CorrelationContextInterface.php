<?php

namespace App\Domain\Correlation\Contracts;

interface CorrelationContextInterface
{
    public function bind(string $correlationId): void;

    public function isBound(): bool;

    public function get(): ?string;

    public function clear(): void;
}
