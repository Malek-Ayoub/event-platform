<?php

namespace App\Services\Outbox\Consumers;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Models\Commission;
use App\Models\OutboxEvent;
use App\Services\Outbox\AbstractOutboxConsumer;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use App\Services\Settlements\Support\SettlementPaymentContextResolver;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Appends commission_due (credit) when a sale commission is recorded (Phase 8.5.1).
 */
final class RecordSettlementOnCommissionRecordedConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private SettlementEntryService $settlementEntryService,
        private SettlementPaymentContextResolver $paymentContextResolver,
    ) {}

    public function consumerKey(): string
    {
        return 'settlement.commission_recorded';
    }

    protected function eventType(): string
    {
        return 'commission.recorded';
    }

    public function handle(OutboxEvent $event): void
    {
        $payload = $this->innerPayload($event);

        $commissionId = (int) ($payload['commission_id'] ?? $event->aggregate_id);

        if ($commissionId === 0) {
            throw new InvalidArgumentException('commission.recorded outbox payload is missing commission_id.');
        }

        $commission = Commission::query()
            ->with('order')
            ->whereKey($commissionId)
            ->firstOrFail();

        $order = $commission->order;
        $paymentContext = $this->paymentContextResolver->resolveForOrder($order);

        $this->settlementEntryService->append(new AppendSettlementEntryData(
            venueId: (int) $commission->venue_id,
            eventId: (int) $order->event_id,
            orderId: (int) $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: (string) $commission->amount,
            currency: $paymentContext['currency'],
            referenceType: 'commission',
            referenceId: (int) $commission->id,
            occurredAt: $this->resolveOccurredAt($event, $commission->created_at),
            paymentTransactionId: $paymentContext['payment_transaction_id'],
            correlationId: $event->correlation_id ?? "commission:{$commission->id}",
            metadata: [
                'commission_rate' => (string) $commission->rate,
                'order_total' => (string) $order->total,
            ],
        ));
    }

    private function resolveOccurredAt(OutboxEvent $event, mixed $fallback): Carbon
    {
        $occurredAt = $event->payload['occurred_at'] ?? null;

        if (is_string($occurredAt) && $occurredAt !== '') {
            return Carbon::parse($occurredAt);
        }

        return Carbon::parse($fallback);
    }
}
