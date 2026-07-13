<?php

namespace Tests\Unit\Services\Commissions;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Exceptions\Settlements\NoOutstandingCommissionException;
use App\Exceptions\Settlements\PaymentExceedsOutstandingCommissionException;
use App\Models\ActivityLog;
use App\Models\CommissionPayment;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentAccount;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\SettlementEntry;
use App\Models\User;
use App\Services\Commissions\CommissionPaymentService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommissionPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_commission_payment_settlement_entry_activity_log_and_outbox(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $this->seedOutstandingCommission($venue->id, '40.00');

        $result = app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '20.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Shamcash,
            receivedAt: Carbon::parse('2026-07-13 12:00:00'),
            receivedBy: $admin,
            referenceNumber: 'SC-12345',
            notes: 'July settlement',
            ipAddress: '127.0.0.1',
        ));

        $payment = $result->payment;
        $this->assertSame('20.00', $payment->amount);
        $this->assertSame(CommissionPaymentMethod::Shamcash, $payment->payment_method);
        $this->assertNull($payment->updated_at);

        $entry = SettlementEntry::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('reference_type', 'commission_payment')
            ->where('reference_id', $payment->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(SettlementEntryType::CommissionPaid, $entry->type);
        $this->assertSame(SettlementEntryDirection::Debit, $entry->direction);
        $this->assertSame('20.00', $entry->amount);
        $this->assertSame('20.00', $result->settlementEntry->balance_after);
        $this->assertNull($entry->order_id);
        $this->assertNull($entry->event_id);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => CommissionPayment::class,
            'entity_id' => $payment->id,
            'action' => 'recorded',
            'venue_id' => $venue->id,
        ]);

        $outbox = OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('event_type', 'commission.payment_recorded')
            ->first();

        $this->assertNotNull($outbox);
        $this->assertSame($payment->id, $outbox->aggregate_id);
    }

    #[Test]
    public function it_rejects_payment_above_outstanding_commission(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $this->seedOutstandingCommission($venue->id, '40.00');

        $this->expectException(PaymentExceedsOutstandingCommissionException::class);

        app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '60.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Cash,
            receivedAt: now(),
            receivedBy: $admin,
        ));
    }

    #[Test]
    public function it_rejects_payment_when_no_outstanding_commission_exists(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();

        $this->expectException(NoOutstandingCommissionException::class);

        app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '10.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Cash,
            receivedAt: now(),
            receivedBy: $admin,
        ));
    }

    #[Test]
    public function it_rejects_payment_account_from_another_venue(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $this->seedOutstandingCommission($venueA->id, '25.00');

        $account = PaymentAccount::factory()->create(['venue_id' => $venueB->id]);

        $this->expectException(InvalidArgumentException::class);

        app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venueA->id,
            amount: '10.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Shamcash,
            receivedAt: now(),
            receivedBy: $admin,
            paymentAccountId: $account->id,
        ));
    }

    #[Test]
    public function partial_payments_reduce_outstanding_until_zero(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $this->seedOutstandingCommission($venue->id, '35.00');
        $service = app(CommissionPaymentService::class);

        $first = $service->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '20.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::BankTransfer,
            receivedAt: now(),
            receivedBy: $admin,
        ));
        $this->assertSame('15.00', $first->settlementEntry->balance_after);

        $second = $service->recordPayment(new RecordCommissionPaymentData(
            venueId: $venue->id,
            amount: '15.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Cash,
            receivedAt: now()->addMinute(),
            receivedBy: $admin,
        ));
        $this->assertSame('0.00', $second->settlementEntry->balance_after);
        $this->assertSame(2, CommissionPayment::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    private function seedOutstandingCommission(int $venueId, string $amount): void
    {
        $this->bindTenant($venueId);

        $event = Event::factory()->create(['venue_id' => $venueId]);
        $order = Order::factory()->forEvent($event)->create();

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venueId,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: $amount,
            currency: 'USD',
            referenceType: 'commission',
            referenceId: fake()->unique()->numberBetween(1000, 9999),
            occurredAt: now(),
        ));
    }
}
