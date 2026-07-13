<?php

namespace Tests\Unit\Services\Commissions;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Commissions\AdjustmentExceedsCommissionException;
use App\Exceptions\Commissions\CommissionNotFoundException;
use App\Exceptions\Commissions\OrderNotEligibleForCommissionException;
use App\Exceptions\Commissions\PaymentNotCompletedException;
use App\Exceptions\Commissions\RefundNotProcessedException;
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
use App\Models\Venue;
use App\Services\ActivityLogService;
use App\Services\Commissions\CommissionService;
use App\Services\Commissions\Data\RecordCommissionAdjustmentData;
use App\Services\Commissions\Data\RecordCommissionData;
use App\Services\OutboxService;
use App\Services\Payments\Data\BeginVerificationData;
use App\Services\Payments\Data\CreateAwaitingTransferData;
use App\Services\Payments\Data\MarkPaidData;
use App\Services\Payments\PaymentService;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\RefundService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_commission_for_completed_payment_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $venue->update(['commission_rate' => 5.00]);
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '200.00');

        $commission = app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame('10.00', $commission->amount);
        $this->assertSame('5.00', $commission->rate);
        $this->assertSame(CommissionStatus::Pending, $commission->status);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => Commission::class,
            'entity_id' => $commission->id,
            'action' => 'recorded',
            'venue_id' => $venue->id,
        ]);

        $outbox = OutboxEvent::query()->where('event_type', 'commission.recorded')->first();
        $this->assertNotNull($outbox);
        $this->assertArrayHasKey('occurred_at', $outbox->payload);
    }

    #[Test]
    public function it_is_idempotent_for_duplicate_commission_on_same_order(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $service = app(CommissionService::class);

        $first = $service->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));
        $second = $service->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Commission::query()->count());
        $this->assertSame(1, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'recorded')->where('entity_type', Commission::class)->count());
    }

    #[Test]
    public function it_rejects_commission_when_order_is_not_paid(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->expectException(OrderNotEligibleForCommissionException::class);

        app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $order->id,
        ));
    }

    #[Test]
    public function it_rejects_commission_when_payment_is_not_completed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'total' => '100.00',
            'subtotal' => '100.00',
        ]);
        $payment = PaymentTransaction::factory()->forOrder($order)->create([
            'amount' => '100.00',
        ]);

        $this->expectException(PaymentNotCompletedException::class);

        app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
        ));
    }

    #[Test]
    public function it_records_commission_adjustment_without_modifying_commission_amount(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $venue->update(['commission_rate' => 10.00]);
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $commissionService = app(CommissionService::class);
        $commission = $commissionService->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));

        $refundService = app(RefundService::class);
        $refund = $refundService->createRefund(new CreateRefundData(
            orderId: $payment->order_id,
            amount: '50.00',
            paymentTransactionId: $payment->id,
        ));
        $refundService->processRefund(new ProcessRefundData($refund->id));

        $adjustment = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));

        $this->assertSame('5.00', $adjustment->adjustment_amount);
        $this->assertSame('10.00', $adjustment->rate_snapshot);
        $this->assertSame('10.00', $commission->fresh()->amount);

        $this->assertTrue(
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'commission.adjusted')->exists(),
        );
    }

    #[Test]
    public function it_is_idempotent_for_duplicate_commission_adjustment_on_same_refund(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $commissionService = app(CommissionService::class);
        $commissionService->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));

        $refundService = app(RefundService::class);
        $refund = $refundService->createRefund(new CreateRefundData(
            orderId: $payment->order_id,
            amount: '40.00',
        ));
        $refundService->processRefund(new ProcessRefundData($refund->id));

        $first = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));
        $second = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CommissionAdjustment::query()->count());
    }

    #[Test]
    public function it_rejects_adjustment_exceeding_remaining_commission_amount(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $venue->update(['commission_rate' => 10.00]);
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $commissionService = app(CommissionService::class);
        $commission = $commissionService->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));

        CommissionAdjustment::factory()->forCommissionAndRefund(
            $commission,
            Refund::factory()->forOrder(Order::query()->findOrFail($payment->order_id))->create([
                'amount' => '90.00',
                'status' => RefundStatus::Processed,
            ]),
        )->create([
            'adjustment_amount' => '9.50',
            'rate_snapshot' => '10.00',
        ]);

        $refundService = app(RefundService::class);
        $refund = $refundService->createRefund(new CreateRefundData(
            orderId: $payment->order_id,
            amount: '10.00',
        ));
        $refundService->processRefund(new ProcessRefundData($refund->id));

        $this->expectException(AdjustmentExceedsCommissionException::class);

        $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));
    }

    #[Test]
    public function it_rejects_adjustment_when_refund_is_not_processed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $commissionService = app(CommissionService::class);
        $commissionService->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));

        $refund = Refund::factory()->forOrder(Order::query()->findOrFail($payment->order_id))->create([
            'amount' => '25.00',
            'status' => RefundStatus::Pending,
        ]);

        $this->expectException(RefundNotProcessedException::class);

        $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));
    }

    #[Test]
    public function it_rejects_adjustment_when_commission_does_not_exist(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');

        $refundService = app(RefundService::class);
        $refund = $refundService->createRefund(new CreateRefundData(
            orderId: $payment->order_id,
            amount: '25.00',
        ));
        $refundService->processRefund(new ProcessRefundData($refund->id));

        $this->expectException(CommissionNotFoundException::class);

        app(CommissionService::class)->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));
    }

    #[Test]
    public function it_rolls_back_commission_when_activity_log_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $commissionCountBefore = Commission::query()->count();
        $outboxCountBefore = OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count();

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('log failed'));
        });

        try {
            app(CommissionService::class)->recordCommission(new RecordCommissionData(
                orderId: $payment->order_id,
                paymentTransactionId: $payment->id,
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($commissionCountBefore, Commission::query()->count());
        $this->assertSame($outboxCountBefore, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_rolls_back_commission_when_outbox_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $commissionCountBefore = Commission::query()->count();
        $activityLogCountBefore = ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count();

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        try {
            app(CommissionService::class)->recordCommission(new RecordCommissionData(
                orderId: $payment->order_id,
                paymentTransactionId: $payment->id,
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($commissionCountBefore, Commission::query()->count());
        $this->assertSame($activityLogCountBefore, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_enforces_cross_tenant_isolation(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueB->id);
        $paymentB = $this->paidPaymentForOrder($venueB, '100.00');

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $paymentB->order_id,
            paymentTransactionId: $paymentB->id,
        ));
    }

    #[Test]
    public function super_admin_cannot_record_commission_for_another_tenants_order(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();

        $this->bindTenant($venueB->id);
        $paymentB = $this->paidPaymentForOrder($venueB, '100.00');

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $paymentB->order_id,
            paymentTransactionId: $paymentB->id,
            actor: $admin,
        ));
    }

    #[Test]
    public function it_does_not_modify_order_payment_or_refund_records(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $venue->update(['commission_rate' => 5.00]);
        $this->bindTenant($venue->id);

        $payment = $this->paidPaymentForOrder($venue, '100.00');
        $orderBefore = Order::query()->findOrFail($payment->order_id);
        $paymentBefore = $payment->fresh()->toArray();

        app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $payment->order_id,
            paymentTransactionId: $payment->id,
        ));

        $this->assertSame($orderBefore->status, Order::query()->findOrFail($payment->order_id)->status);
        $this->assertSame($orderBefore->total, Order::query()->findOrFail($payment->order_id)->total);
        $this->assertSame($paymentBefore['status'], PaymentTransaction::query()->findOrFail($payment->id)->status->value);
    }

    private function paidPaymentForOrder(Venue $venue, string $total): PaymentTransaction
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => $total,
            'subtotal' => $total,
            'status' => OrderStatus::Pending,
        ]);

        $paymentService = app(PaymentService::class);
        $payment = $paymentService->createAwaitingTransfer(new CreateAwaitingTransferData(
            orderId: $order->id,
            provider: 'apisyria',
            amount: $total,
            currency: 'USD',
            expiresAt: now()->addHour(),
        ));
        $paymentService->beginVerification(new BeginVerificationData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-'.uniqid(),
        ));
        $paymentService->markPaid(new MarkPaidData(
            paymentTransactionId: $payment->id,
            providerTransactionId: 'APISYRIA-'.uniqid(),
        ));

        return $payment->fresh();
    }
}
