<?php

namespace App\Services\Outbox\Consumers;

use App\Models\Order;
use App\Models\OutboxEvent;
use App\Services\Notifications\Mapping\OrderPaidEmailNotificationMapper;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Outbox\AbstractOutboxConsumer;
use InvalidArgumentException;

/**
 * Sends the order.paid confirmation email via the notification channel layer (Phase 8.2.2).
 */
final class SendOrderPaidEmailConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private NotificationDispatcher $notificationDispatcher,
        private OrderPaidEmailNotificationMapper $mapper,
    ) {}

    public function consumerKey(): string
    {
        return 'notification.email.order_paid';
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

        $order = Order::query()->whereKey($orderId)->firstOrFail();

        $this->notificationDispatcher->dispatch(
            $this->mapper->toMessage($order),
        );
    }
}
