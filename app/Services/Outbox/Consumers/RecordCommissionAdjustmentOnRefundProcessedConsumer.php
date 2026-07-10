<?php

namespace App\Services\Outbox\Consumers;

use App\Models\OutboxEvent;
use App\Services\Commissions\CommissionService;
use App\Services\Commissions\Data\RecordCommissionAdjustmentData;
use App\Services\Outbox\AbstractOutboxConsumer;
use InvalidArgumentException;

/**
 * Records commission adjustment when a refund is processed (Phase 8.1).
 */
final class RecordCommissionAdjustmentOnRefundProcessedConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private CommissionService $commissionService,
    ) {}

    public function eventType(): string
    {
        return 'refund.processed';
    }

    public function consume(OutboxEvent $event): void
    {
        $payload = $this->innerPayload($event);

        $refundId = (int) ($payload['refund_id'] ?? 0);

        if ($refundId === 0) {
            throw new InvalidArgumentException('refund.processed outbox payload is missing refund_id.');
        }

        $this->commissionService->recordAdjustment(new RecordCommissionAdjustmentData(
            refundId: $refundId,
        ));
    }
}
