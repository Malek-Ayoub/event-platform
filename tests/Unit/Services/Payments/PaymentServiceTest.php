<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Payments\InvalidPaymentStateTransitionException;
use App\Exceptions\Payments\OrderNotPayableException;
use App\Exceptions\Payments\PaymentProviderMismatchException;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\FailPaymentData;
use App\Services\Payments\Data\InitiatePaymentData;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PaymentTransactionStateMachine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_initiates_payment_transaction_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '150.00',
            'subtotal' => '150.00',
            'status' => OrderStatus::Pending,
        ]);

        $payment = app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-12345',
            amount: '150.00',
            currency: 'USD',
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame($order->id, $payment->order_id);
        $this->assertSame(PaymentTransactionStatus::Pending, $payment->status);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => PaymentTransaction::class,
            'entity_id' => $payment->id,
            'action' => 'initiated',
            'actor_user_id' => $owner->id,
            'venue_id' => $venue->id,
        ]);

        $outbox = OutboxEvent::query()->where('aggregate_id', $payment->id)->first();
        $this->assertNotNull($outbox);
        $this->assertSame('payment.initiated', $outbox->event_type);
        $this->assertSame('payment_transaction', $outbox->payload['aggregate']);
    }

    #[Test]
    public function it_is_idempotent_for_duplicate_provider_transaction_id(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $service = app(PaymentService::class);
        $data = new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-DUP-1',
            amount: '100.00',
            currency: 'USD',
        );

        $first = $service->initiatePayment($data);
        $second = $service->initiatePayment($data);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame(1, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_rejects_duplicate_provider_transaction_for_different_order(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $orderA = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);
        $orderB = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
            orderId: $orderA->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-SAME',
            amount: '100.00',
            currency: 'USD',
        ));

        $this->expectException(PaymentProviderMismatchException::class);

        app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
            orderId: $orderB->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-SAME',
            amount: '100.00',
            currency: 'USD',
        ));
    }

    #[Test]
    public function completing_payment_updates_order_once_and_emits_outbox_events(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->initiatePayment(new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-PAID-1',
            amount: '100.00',
            currency: 'USD',
        ));

        $completed = $service->completePayment(new CompletePaymentData(
            paymentTransactionId: $payment->id,
            paymentMethod: 'shamcash',
            paymentReference: 'TXN-PAID-1',
        ));

        $this->assertSame(PaymentTransactionStatus::Completed, $completed->status);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame('shamcash', $order->fresh()->payment_method);

        $this->assertSame(3, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
        $this->assertTrue(
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'payment.completed')->exists(),
        );
        $this->assertTrue(
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->exists(),
        );
    }

    #[Test]
    public function completing_payment_twice_is_fully_idempotent_for_side_effects(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->initiatePayment(new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-WEBHOOK-DUP',
            amount: '100.00',
            currency: 'USD',
        ));

        $service->completePayment(new CompletePaymentData($payment->id));
        $service->completePayment(new CompletePaymentData($payment->id));

        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(
            1,
            ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'completed')->count(),
        );
        $this->assertSame(
            1,
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'payment.completed')->count(),
        );
        $this->assertSame(
            1,
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count(),
        );
    }

    #[Test]
    public function completing_payment_twice_is_idempotent_for_order_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->initiatePayment(new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-IDEM-1',
            amount: '100.00',
            currency: 'USD',
        ));

        $service->completePayment(new CompletePaymentData($payment->id));
        $service->completePayment(new CompletePaymentData($payment->id));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'completed')->count());
    }

    #[Test]
    public function it_rejects_invalid_payment_state_transition(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $payment = PaymentTransaction::factory()->forOrder($order)->failed()->create();

        $this->expectException(InvalidPaymentStateTransitionException::class);

        app(PaymentService::class)->completePayment(new CompletePaymentData($payment->id));
    }

    #[Test]
    public function it_rolls_back_when_activity_log_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('log failed'));
        });

        try {
            app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
                orderId: $order->id,
                provider: 'shamcash',
                providerTransactionId: 'TXN-ROLLBACK',
                amount: '100.00',
                currency: 'USD',
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, PaymentTransaction::query()->count());
        $this->assertSame(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        try {
            app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
                orderId: $order->id,
                provider: 'shamcash',
                providerTransactionId: 'TXN-OUTBOX-FAIL',
                amount: '100.00',
                currency: 'USD',
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, PaymentTransaction::query()->count());
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_enforces_cross_tenant_isolation_for_orders(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $orderB = Order::factory()->forEvent($eventB)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
            orderId: $orderB->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-CROSS',
            amount: '100.00',
            currency: 'USD',
        ));
    }

    #[Test]
    public function super_admin_cannot_initiate_payment_for_another_tenants_order(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $orderB = Order::factory()->forEvent($eventB)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
            orderId: $orderB->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-ADMIN',
            amount: '100.00',
            currency: 'USD',
            actor: $admin,
        ));
    }

    #[Test]
    public function failing_payment_does_not_change_order_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $service = app(PaymentService::class);
        $payment = $service->initiatePayment(new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-FAIL-1',
            amount: '100.00',
            currency: 'USD',
        ));

        $service->failPayment(new FailPaymentData(
            paymentTransactionId: $payment->id,
            reason: 'declined',
        ));

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Failed, $payment->fresh()->status);
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

    #[Test]
    public function it_rejects_payment_for_non_pending_order(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->expectException(OrderNotPayableException::class);

        app(PaymentService::class)->initiatePayment(new InitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            providerTransactionId: 'TXN-NOPE',
            amount: '100.00',
            currency: 'USD',
        ));
    }
}
