<?php

namespace App\Services\Outbox;

use App\Contracts\Outbox\OutboxConsumer;
use App\Models\OutboxEvent;

abstract class AbstractOutboxConsumer implements OutboxConsumer, SupportsOutboxEventType
{
    public function supports(string $eventType): bool
    {
        return $eventType === $this->eventType();
    }

    /**
     * @return array<string, mixed>
     */
    protected function innerPayload(OutboxEvent $event): array
    {
        /** @var array<string, mixed> $payload */
        $payload = (array) ($event->payload['payload'] ?? []);

        return $payload;
    }
}
