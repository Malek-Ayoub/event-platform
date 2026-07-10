<?php

namespace App\Contracts\Outbox;

use App\Models\OutboxEvent;

/**
 * Handles a single outbox event type outside any domain transaction.
 *
 * Consumers are invoked exclusively by {@see \App\Services\Outbox\OutboxDispatcher}.
 */
interface OutboxConsumer
{
    public function supports(string $eventType): bool;

    public function consume(OutboxEvent $event): void;
}
