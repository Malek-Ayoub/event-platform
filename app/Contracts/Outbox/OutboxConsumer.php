<?php

namespace App\Contracts\Outbox;

use App\Models\OutboxEvent;

/**
 * Handles a single outbox event outside any domain transaction.
 *
 * Consumers are invoked exclusively by {@see \App\Services\Outbox\OutboxDispatcher}.
 * Idempotency is enforced per consumer via {@see \App\Repositories\ConsumerReceiptRepository}.
 */
interface OutboxConsumer
{
    public function consumerKey(): string;

    public function supports(string $eventType): bool;

    public function handle(OutboxEvent $event): void;
}
