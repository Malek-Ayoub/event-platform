<?php

namespace App\Contracts\Outbox;

use App\Models\OutboxEvent;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Outbox\OutboxDispatcher;

/**
 * Handles a single outbox event outside any domain transaction.
 *
 * Consumers are invoked exclusively by {@see OutboxDispatcher}.
 * Idempotency is enforced per consumer via {@see ConsumerReceiptRepository}.
 */
interface OutboxConsumer
{
    public function consumerKey(): string;

    public function supports(string $eventType): bool;

    public function handle(OutboxEvent $event): void;
}
