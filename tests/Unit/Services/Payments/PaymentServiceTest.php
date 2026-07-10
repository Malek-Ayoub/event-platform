<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Payments\InvalidPaymentStateTransitionException;
use App\Exceptions\Payments\OrderNotPayableException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Payments\Data\BeginVerificationData;
use App\Services\Payments\Data\CreateAwaitingTransferData;
use App\Services\Payments\Data\MarkPaidData;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentTransactionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_awaiting_transfer_payment_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '150.00',
            'subtotal' => '150.00',
            'status' => OrderStatus::Pending,
        ]);

        $payment = app(PaymentService::class)->createAwaitingTransfer(new CreateAwaitingTransferData(
            orderId: $order->id,
            provider: 'apisyria',
            amount: '150.00',
            currency: 'USD',
            expiresAt: now()->addHour(),
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame(PaymentTransactionStatus::AwaitingTransfer, $payment->status);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => PaymentTransaction::class,
            'entity_id' => $payment->id,
            'action' => 'awaiting_transfer',
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'payment.awaiting_transfer',
        ]);
    }

    #[Test]
    public function mark_paid_updates_order_once_and_emits_outbox_events(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->awaitingPayment($venue, '100.00');

        $service = app(PaymentService::class);
        $service->beginVerification(new BeginVerificationData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-PAID-1',
        ));

        $service->markPaid(new MarkPaidData(
            paymentTransactionId: $payment->id,
            providerTransactionId: 'APISYRIA-TX-PAID-1',
            actor: $owner,
        ));

        $this->assertSame(OrderStatus::Paid, $payment->order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Paid, $payment->fresh()->status);

        $this->assertSame(
            1,
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count(),
        );
    }

    #[Test]
    public function mark_paid_is_idempotent(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->awaitingPayment($venue, '100.00');
        $service = app(PaymentService::class);

        $service->beginVerification(new BeginVerificationData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-IDEM',
        ));
        $service->markPaid(new MarkPaidData(
            paymentTransactionId: $payment->id,
            providerTransactionId: 'APISYRIA-TX-IDEM',
        ));
        $service->markPaid(new MarkPaidData(
            paymentTransactionId: $payment->id,
            providerTransactionId: 'APISYRIA-TX-IDEM',
        ));

        $this->assertSame(
            1,
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count(),
        );
    }

    #[Test]
    public function it_rejects_mark_paid_for_non_pending_order(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->awaitingPayment($venue, '100.00');
        $payment->order->update(['status' => OrderStatus::Paid]);

        $service = app(PaymentService::class);
        $service->beginVerification(new BeginVerificationData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-BAD-ORDER',
        ));

        $this->expectException(OrderNotPayableException::class);

        $service->markPaid(new MarkPaidData(
            paymentTransactionId: $payment->id,
            providerTransactionId: 'APISYRIA-TX-BAD',
        ));
    }

    #[Test]
    public function it_rolls_back_when_activity_log_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->awaitingPayment($venue, '100.00');

        $service = app(PaymentService::class);
        $service->beginVerification(new BeginVerificationData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-ROLLBACK',
        ));

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('audit failed'));
        });
        $this->app->forgetInstance(PaymentService::class);

        try {
            app(PaymentService::class)->markPaid(new MarkPaidData(
                paymentTransactionId: $payment->id,
                providerTransactionId: 'APISYRIA-TX-ROLLBACK',
            ));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(PaymentTransactionStatus::Verifying, $payment->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $payment->order->fresh()->status);
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->awaitingPayment($venue, '100.00');

        $service = app(PaymentService::class);
        $service->beginVerification(new BeginVerificationData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-OUTBOX',
        ));

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });
        $this->app->forgetInstance(PaymentService::class);

        try {
            app(PaymentService::class)->markPaid(new MarkPaidData(
                paymentTransactionId: $payment->id,
                providerTransactionId: 'APISYRIA-TX-OUTBOX',
            ));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(PaymentTransactionStatus::Verifying, $payment->fresh()->status);
    }

    #[Test]
    public function payment_state_machine_rejects_completed_to_pending(): void
    {
        $machine = new PaymentTransactionStateMachine;

        $this->expectException(InvalidPaymentStateTransitionException::class);

        $machine->assertCanTransition(
            PaymentTransactionStatus::Completed,
            PaymentTransactionStatus::Pending,
        );
    }

    private function awaitingPayment(mixed $venue, string $total): PaymentTransaction
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => $total,
            'subtotal' => $total,
            'status' => OrderStatus::Pending,
        ]);

        return app(PaymentService::class)->createAwaitingTransfer(new CreateAwaitingTransferData(
            orderId: $order->id,
            provider: 'apisyria',
            amount: $total,
            currency: 'USD',
            expiresAt: now()->addHour(),
        ));
    }
}
