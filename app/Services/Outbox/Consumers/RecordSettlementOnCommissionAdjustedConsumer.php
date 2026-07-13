<?php

namespace App\Services\Outbox\Consumers;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Models\CommissionAdjustment;
use App\Models\OutboxEvent;
use App\Services\Outbox\AbstractOutboxConsumer;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use App\Services\Settlements\Support\SettlementPaymentContextResolver;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Appends commission_adjustment (debit) when a refund reduces commission due (Phase 8.5.1).
 */
final class RecordSettlementOnCommissionAdjustedConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private SettlementEntryService $settlementEntryService,
        private SettlementPaymentContextResolver $paymentContextResolver,
    ) {}

    public function consumerKey(): string
    {
        return 'settlement.commission_adjusted';
    }

    protected function eventType(): string
    {
        return 'commission.adjusted';
    }

    public function handle(OutboxEvent $event): void
    {
        $adjustmentId = (int) $event->aggregate_id;

        if ($adjustmentId === 0) {
            throw new InvalidArgumentException('commission.adjusted outbox event is missing aggregate_id.');
        }

        $adjustment = CommissionAdjustment::query()
            ->with(['commission.order', 'refund'])
            ->whereKey($adjustmentId)
            ->firstOrFail();

        $commission = $adjustment->commission;
        $order = $commission->order;
        $paymentContext = $this->paymentContextResolver->resolveForOrder(
            $order,
            $adjustment->refund->payment_transaction_id,
        );

        $this->settlementEntryService->append(new AppendSettlementEntryData(
            venueId: (int) $adjustment->venue_id,
            eventId: (int) $order->event_id,
            orderId: (int) $order->id,
            type: SettlementEntryType::CommissionAdjustment,
            direction: SettlementEntryDirection::Debit,
            amount: (string) $adjustment->adjustment_amount,
            currency: $paymentContext['currency'],
            referenceType: 'commission_adjustment',
            referenceId: (int) $adjustment->id,
            occurredAt: $this->resolveOccurredAt($event, $adjustment->created_at),
            paymentTransactionId: $paymentContext['payment_transaction_id'],
            correlationId: $event->correlation_id ?? "commission_adjustment:{$adjustment->id}",
            metadata: [
                'commission_id' => $commission->id,
                'refund_id' => $adjustment->refund_id,
                'commission_rate' => (string) $adjustment->rate_snapshot,
                'refund_amount' => (string) $adjustment->refund->amount,
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
