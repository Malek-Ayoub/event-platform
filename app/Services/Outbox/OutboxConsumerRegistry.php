<?php

namespace App\Services\Outbox;

use App\Contracts\Outbox\OutboxConsumer;

final class OutboxConsumerRegistry
{
    /** @var array<string, OutboxConsumer> */
    private array $consumersByEventType = [];

    public function register(OutboxConsumer $consumer): void
    {
        if ($consumer instanceof SupportsOutboxEventType) {
            $this->consumersByEventType[$consumer->eventType()] = $consumer;

            return;
        }

        throw new \InvalidArgumentException('Outbox consumer must implement SupportsOutboxEventType.');
    }

    public function resolve(string $eventType): ?OutboxConsumer
    {
        return $this->consumersByEventType[$eventType] ?? null;
    }
}
