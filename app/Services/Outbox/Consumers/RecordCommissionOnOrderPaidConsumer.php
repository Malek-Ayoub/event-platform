<?php

namespace App\Services\Outbox\Consumers;

use App\Models\OutboxEvent;
use App\Services\Commissions\CommissionService;
use App\Services\Commissions\Data\RecordCommissionData;
use App\Services\Outbox\AbstractOutboxConsumer;
use InvalidArgumentException;

/**
 * Records venue commission when an order is marked paid (Phase 8.1).
 */
final class RecordCommissionOnOrderPaidConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private CommissionService $commissionService,
    ) {}

    public function consumerKey(): string
    {
        return 'commission.order_paid';
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

        $paymentTransactionId = isset($payload['payment_transaction_id'])
            ? (int) $payload['payment_transaction_id']
            : null;

        $this->commissionService->recordCommission(new RecordCommissionData(
            orderId: $orderId,
            paymentTransactionId: $paymentTransactionId,
        ));
    }
}
