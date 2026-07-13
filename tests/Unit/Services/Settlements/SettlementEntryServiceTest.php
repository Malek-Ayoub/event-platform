<?php

namespace Tests\Unit\Services\Settlements;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\SettlementEntry;
use App\Services\Commissions\CommissionService;
use App\Services\Commissions\Data\RecordCommissionData;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\RefundService;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettlementEntryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_appends_commission_due_credit_with_outstanding_balance(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create(['total' => '200.00']);
        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'amount' => '200.00',
            'currency' => 'USD',
        ]);
        $commission = Commission::factory()->forOrder($order)->create([
            'amount' => '10.00',
            'rate' => '5.00',
        ]);

        $service = app(SettlementEntryService::class);

        $entry = $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '10.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: $commission->id,
            occurredAt: now(),
            paymentTransactionId: $payment->id,
            correlationId: "commission:{$commission->id}",
            metadata: ['commission_rate' => '5.00'],
        ));

        $this->assertSame(SettlementEntryType::CommissionDue, $entry->type);
        $this->assertSame(SettlementEntryDirection::Credit, $entry->direction);
        $this->assertSame('10.00', $entry->amount);
        $this->assertSame('10.00', $entry->balance_after);
        $this->assertSame("commission:{$commission->id}", $entry->correlation_id);
        $this->assertSame(['commission_rate' => '5.00'], $entry->metadata);
        $this->assertNull($entry->updated_at);
    }

    #[Test]
    public function it_applies_commission_adjustment_debit_against_outstanding_balance(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $service = app(SettlementEntryService::class);

        $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '10.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 1,
            occurredAt: now(),
        ));

        $adjustmentEntry = $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionAdjustment,
            direction: SettlementEntryDirection::Debit,
            amount: '2.00',
            currency: 'USD',
            referenceType: 'commission_adjustment',
            referenceId: 1,
            occurredAt: now()->addSecond(),
        ));

        $this->assertSame('8.00', $adjustmentEntry->balance_after);
    }

    #[Test]
    public function commission_paid_debit_reduces_outstanding_balance_to_zero(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $service = app(SettlementEntryService::class);

        $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '1000.00',
            currency: 'SYP',
            referenceType: 'commission',
            referenceId: 1,
            occurredAt: now(),
        ));

        $paidEntry = $service->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionPaid,
            direction: SettlementEntryDirection::Debit,
            amount: '1000.00',
            currency: 'SYP',
            referenceType: 'commission_payment',
            referenceId: 1,
            occurredAt: now()->addSecond(),
            metadata: ['recorded_by' => 'admin'],
        ));

        $this->assertSame(SettlementEntryType::CommissionPaid, $paidEntry->type);
        $this->assertSame('0.00', $paidEntry->balance_after);
    }

    #[Test]
    public function it_is_idempotent_for_duplicate_reference(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $service = app(SettlementEntryService::class);
        $data = new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '10.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 99,
            occurredAt: now(),
        );

        $first = $service->append($data);
        $second = $service->append($data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, SettlementEntry::query()->count());
        $this->assertSame('10.00', $second->balance_after);
    }

    #[Test]
    public function outbox_consumers_record_commission_due_and_adjustment_without_refund_rows(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $venue->update(['commission_rate' => 10.00]);
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $order = $payment->order;
        $order->update(['status' => OrderStatus::Paid]);

        $commission = app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
        ));

        $this->dispatchOutbox();

        $commissionEntry = SettlementEntry::query()
            ->where('reference_type', 'commission')
            ->where('reference_id', $commission->id)
            ->first();

        $this->assertNotNull($commissionEntry);
        $this->assertSame(SettlementEntryType::CommissionDue, $commissionEntry->type);
        $this->assertSame(SettlementEntryDirection::Credit, $commissionEntry->direction);
        $this->assertSame('10.00', $commissionEntry->amount);
        $this->assertSame('10.00', $commissionEntry->balance_after);

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '50.00',
            reason: 'customer request',
            actor: $owner,
        ));

        app(RefundService::class)->processRefund(new ProcessRefundData(
            refundId: $refund->id,
            providerRefundId: 'RF-001',
            actor: $owner,
        ));

        $this->dispatchOutbox();
        $this->dispatchOutbox();

        $this->assertSame(
            0,
            SettlementEntry::query()->where('type', SettlementEntryType::CommissionPaid)->count(),
        );
        $this->assertSame(
            0,
            SettlementEntry::query()->where('reference_type', 'refund')->count(),
        );

        $adjustment = CommissionAdjustment::query()->where('refund_id', $refund->id)->first();
        $this->assertNotNull($adjustment);

        $adjustmentEntry = SettlementEntry::query()
            ->where('reference_type', 'commission_adjustment')
            ->where('reference_id', $adjustment->id)
            ->first();

        $this->assertNotNull($adjustmentEntry);
        $this->assertSame(SettlementEntryType::CommissionAdjustment, $adjustmentEntry->type);
        $this->assertSame(SettlementEntryDirection::Debit, $adjustmentEntry->direction);
        $this->assertSame('5.00', $adjustmentEntry->amount);
        $this->assertSame('5.00', $adjustmentEntry->balance_after);
    }

    #[Test]
    public function settlement_consumer_reuses_outbox_correlation_id_when_present(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);
        $payment = PaymentTransaction::factory()->forOrder($order)->create([
            'status' => PaymentTransactionStatus::Completed,
            'amount' => '100.00',
        ]);
        $commission = Commission::factory()->forOrder($order)->create([
            'status' => CommissionStatus::Pending,
            'amount' => '5.00',
        ]);

        OutboxEvent::factory()->create([
            'venue_id' => $venue->id,
            'correlation_id' => 'payment:123',
            'event_type' => 'commission.recorded',
            'aggregate_type' => Commission::class,
            'aggregate_id' => $commission->id,
            'payload' => [
                'aggregate' => 'commission',
                'aggregate_id' => $commission->id,
                'event' => 'commission.recorded',
                'version' => 1,
                'occurred_at' => Carbon::now()->toIso8601String(),
                'payload' => [
                    'order_id' => $order->id,
                    'commission_id' => $commission->id,
                    'amount' => '5.00',
                    'rate' => '5.00',
                ],
            ],
        ]);

        $this->dispatchOutbox();

        $entry = SettlementEntry::query()->where('reference_id', $commission->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame('payment:123', $entry->correlation_id);
        $this->assertSame($payment->id, $entry->payment_transaction_id);
    }

    private function paidPaymentForOrder($venue, string $amount): PaymentTransaction
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => $amount,
            'status' => OrderStatus::Paid,
        ]);

        return PaymentTransaction::factory()->forOrder($order)->create([
            'status' => PaymentTransactionStatus::Completed,
            'amount' => $amount,
            'currency' => 'USD',
        ]);
    }

    private function dispatchOutbox(): void
    {
        app(OutboxDispatcher::class)->dispatchPending();
    }
}
