<?php

namespace App\Services\Outbox\Consumers;

use App\Models\OutboxEvent;
use App\Services\Orders\IssueTicketsService;
use App\Services\Outbox\AbstractOutboxConsumer;
use InvalidArgumentException;

/**
 * Issues tickets when an order is marked paid (Phase 8.3.1).
 */
final class IssueTicketsOnOrderPaidConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private IssueTicketsService $issueTicketsService,
    ) {}

    public function consumerKey(): string
    {
        return 'tickets.issue_on_order_paid';
    }

    protected function eventType(): string
    {
        return 'order.paid';
    }

    public function handle(OutboxEvent $event): void
    {
        $payload = $this->innerPayload($event);

        $orderId = (int) ($payload['order_id'] ?? 0);

        if ($orderId === 0) {
            throw new InvalidArgumentException('order.paid outbox payload is missing order_id.');
        }

        $this->issueTicketsService->issueForPaidOrder($orderId);
    }
}
