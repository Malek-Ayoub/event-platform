<?php

namespace Tests\Unit\Services\Refunds;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Refunds\InvalidRefundStateTransitionException;
use App\Exceptions\Refunds\RefundAmountExceedsOrderException;
use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\RefundService;
use App\Services\Refunds\RefundStateMachine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_refund_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);
        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'amount' => '100.00',
        ]);

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '50.00',
            paymentTransactionId: $payment->id,
            reason: 'Customer request',
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame(RefundStatus::Pending, $refund->status);
        $this->assertSame('50.00', $refund->amount);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => Refund::class,
            'entity_id' => $refund->id,
            'action' => 'created',
            'venue_id' => $venue->id,
        ]);

        $outbox = OutboxEvent::query()->where('aggregate_id', $refund->id)->first();
        $this->assertNotNull($outbox);
        $this->assertSame('refund.created', $outbox->event_type);
    }

    #[Test]
    public function processing_full_refund_updates_order_and_payment_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);
        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'amount' => '100.00',
        ]);

        $service = app(RefundService::class);
        $refund = $service->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '100.00',
            paymentTransactionId: $payment->id,
        ));

        $processed = $service->processRefund(new ProcessRefundData($refund->id, providerRefundId: 'RF-1'));

        $this->assertSame(RefundStatus::Processed, $processed->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Refunded, $payment->fresh()->status);

        $this->assertTrue(
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'refund.processed')->exists(),
        );
    }

    #[Test]
    public function processing_partial_refund_keeps_order_paid(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $service = app(RefundService::class);
        $refund = $service->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '40.00',
        ));

        $service->processRefund(new ProcessRefundData($refund->id));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    #[Test]
    public function it_rejects_cumulative_refunds_exceeding_order_total(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $service = app(RefundService::class);
        $service->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '80.00',
        ));

        $this->expectException(RefundAmountExceedsOrderException::class);

        $service->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '50.00',
        ));
    }

    #[Test]
    public function it_rejects_refund_amount_exceeding_order_total(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->expectException(RefundAmountExceedsOrderException::class);

        app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '150.00',
        ));
    }

    #[Test]
    public function it_rejects_invalid_refund_state_transition(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);
        $refund = Refund::factory()->forOrder($order)->create([
            'amount' => '100.00',
            'status' => RefundStatus::Failed,
        ]);

        $this->expectException(InvalidRefundStateTransitionException::class);

        app(RefundService::class)->processRefund(new ProcessRefundData($refund->id));
    }

    #[Test]
    public function it_rolls_back_when_activity_log_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('log failed'));
        });

        try {
            app(RefundService::class)->createRefund(new CreateRefundData(
                orderId: $order->id,
                amount: '25.00',
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Refund::query()->count());
        $this->assertSame(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        try {
            app(RefundService::class)->createRefund(new CreateRefundData(
                orderId: $order->id,
                amount: '25.00',
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Refund::query()->count());
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_enforces_cross_tenant_isolation(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $orderB = Order::factory()->forEvent($eventB)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $orderB->id,
            amount: '10.00',
        ));
    }

    #[Test]
    public function super_admin_cannot_create_refund_for_another_tenants_order(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $orderB = Order::factory()->forEvent($eventB)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $orderB->id,
            amount: '10.00',
            actor: $admin,
        ));
    }

    #[Test]
    public function refund_service_does_not_create_commission_adjustments(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);
        Commission::factory()->forOrder($order)->create();

        $service = app(RefundService::class);
        $refund = $service->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '100.00',
        ));
        $service->processRefund(new ProcessRefundData($refund->id));

        $this->assertSame(0, CommissionAdjustment::query()->count());
    }

    #[Test]
    public function refund_state_machine_rejects_processed_to_pending(): void
    {
        $machine = new RefundStateMachine;

        $this->expectException(InvalidRefundStateTransitionException::class);

        $machine->assertCanTransition(
            RefundStatus::Processed,
            RefundStatus::Pending,
        );
    }
}
