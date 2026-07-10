<?php

namespace Tests\Feature\Payments;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\Commissions\CommissionService;
use App\Services\Commissions\Data\RecordCommissionAdjustmentData;
use App\Services\Commissions\Data\RecordCommissionData;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\RefundService;
use App\Support\Payments\PaymentCorrelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

/**
 * Batch 7.8 — Manual Wallet Transfer E2E (IMPLEMENTATION_ROADMAP.md §7.9.11).
 *
 * Covers the full HTTP path: instructions → verify → paid, failure cases,
 * idempotency, commission/refund orchestration, and correlation IDs.
 */
class PaymentFlowE2ETest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        $this->configureApiSyriaGateway();
    }

    #[Test]
    public function manual_transfer_happy_path_marks_order_paid_with_shared_correlation(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-HAPPY-001';
        $this->fakeApiSyriaFindTx($transactionNumber, [
            'amount' => '120.00',
            'tran_id' => 'APISYRIA-'.$transactionNumber,
        ]);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);

        $this->verifyPayment($token, $paymentId, $transactionNumber)
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Paid->value)
            ->assertJsonPath('data.transaction_number', $transactionNumber);

        $correlationId = PaymentCorrelation::forProviderTransaction('apisyria', $transactionNumber);

        $payment = PaymentTransaction::withoutGlobalScopes()->findOrFail($paymentId);
        $this->assertSame($transactionNumber, $payment->transaction_number);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->assertDatabaseHas('activity_logs', [
            'correlation_id' => $correlationId,
            'action' => 'verifying',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'correlation_id' => $correlationId,
            'action' => 'paid',
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'correlation_id' => $correlationId,
            'event_type' => 'payment.paid',
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'correlation_id' => $correlationId,
            'event_type' => 'order.paid',
        ]);
    }

    #[Test]
    public function verify_rejects_duplicate_transaction_number_across_payments(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $firstOrder] = $this->createPendingOrderForPayments($venue);
        ['order' => $secondOrder] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-DUP-001';
        $this->fakeApiSyriaFindTx($transactionNumber);

        ['payment_id' => $firstPaymentId] = $this->createPaymentInstructions($token, $firstOrder);
        ['payment_id' => $secondPaymentId] = $this->createPaymentInstructions($token, $secondOrder);

        $this->verifyPayment($token, $firstPaymentId, $transactionNumber)->assertOk();

        $this->verifyPayment($token, $secondPaymentId, $transactionNumber)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Transaction number [TX-E2E-DUP-001] has already been used for another payment.');

        $this->assertSame(OrderStatus::Pending, $secondOrder->fresh()->status);
        $this->assertSame(1, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count());
    }

    #[Test]
    public function verify_marks_payment_failed_when_transaction_not_found_without_order_update(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-NOT-FOUND';
        $this->fakeApiSyriaFindTxNotFound($transactionNumber);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);

        $this->verifyPayment($token, $paymentId, $transactionNumber)
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'payment.paid')->count());
        $this->assertSame(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count());
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'paid')->count());
    }

    #[Test]
    public function verify_marks_payment_failed_on_amount_mismatch(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-AMT-MISMATCH';
        $this->fakeApiSyriaFindTx($transactionNumber, ['amount' => '50.00']);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);

        $this->verifyPayment($token, $paymentId, $transactionNumber)
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    #[Test]
    public function verify_marks_payment_failed_on_currency_mismatch(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-CUR-MISMATCH';
        $this->fakeApiSyriaFindTx($transactionNumber, ['currency' => 'EUR']);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);

        $this->verifyPayment($token, $paymentId, $transactionNumber)
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    #[Test]
    public function verify_expires_payment_without_calling_gateway(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        Http::fake();

        $payment = PaymentTransaction::factory()->forOrder($order)->awaitingTransfer()->create([
            'venue_id' => $venue->id,
            'provider' => 'apisyria',
            'amount' => '120.00',
            'currency' => 'USD',
            'expires_at' => now()->subHour(),
        ]);

        $this->verifyPayment($token, $payment->id, 'TX-E2E-EXPIRED')
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Expired->value);

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    #[Test]
    public function verify_is_idempotent_for_already_paid_payment(): void
    {
        ['token' => $token, 'owner' => $owner, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        $venue->update(['commission_rate' => 5.00]);
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-IDEM-001';
        $this->fakeApiSyriaFindTx($transactionNumber);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);

        $this->verifyPayment($token, $paymentId, $transactionNumber)->assertOk();
        $this->verifyPayment($token, $paymentId, $transactionNumber)->assertOk();

        $this->assertSame(1, PaymentTransaction::query()->where('transaction_number', $transactionNumber)->count());

        $payment = PaymentTransaction::query()->findOrFail($paymentId);
        $commissionService = app(CommissionService::class);

        $first = $commissionService->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));
        $second = $commissionService->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Commission::query()->count());
        $this->assertSame(1, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count());
    }

    #[Test]
    public function paid_order_records_single_commission_when_worker_orchestration_runs(): void
    {
        ['token' => $token, 'owner' => $owner, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        $venue->update(['commission_rate' => 5.00]);
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-E2E-COMM-001';
        $this->fakeApiSyriaFindTx($transactionNumber);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);
        $this->verifyPayment($token, $paymentId, $transactionNumber)->assertOk();

        $this->bindTenant($venue->id);

        $payment = PaymentTransaction::withoutGlobalScopes()->findOrFail($paymentId);
        $commissionService = app(CommissionService::class);

        $first = $commissionService->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));
        $second = $commissionService->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Commission::query()->count());
        $this->assertSame('6.00', $first->amount);
        $this->assertSame(CommissionStatus::Pending, $first->status);
    }

    #[Test]
    public function processed_refund_records_single_commission_adjustment(): void
    {
        ['owner' => $owner, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        $venue->update(['commission_rate' => 10.00]);
        ['order' => $order] = $this->createPendingOrderForPayments($venue, '200.00');

        $order->update(['status' => OrderStatus::Paid]);

        $payment = PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'venue_id' => $venue->id,
            'provider' => 'apisyria',
            'provider_transaction_id' => 'APISYRIA-TX-REFUND',
            'transaction_number' => 'TX-E2E-REFUND',
            'amount' => '200.00',
        ]);

        app(CommissionService::class)->recordCommission(new RecordCommissionData(
            orderId: $order->id,
            paymentTransactionId: $payment->id,
        ));

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '200.00',
            paymentTransactionId: $payment->id,
        ));

        app(RefundService::class)->processRefund(new ProcessRefundData(
            refundId: $refund->id,
            providerRefundId: 'APISYRIA-REF-001',
        ));

        $commissionService = app(CommissionService::class);

        $first = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));
        $second = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CommissionAdjustment::query()->count());
        $this->assertSame('20.00', $first->adjustment_amount);
        $this->assertSame(RefundStatus::Processed, $refund->fresh()->status);
    }

    #[Test]
    public function initiating_payment_twice_via_api_is_idempotent_for_awaiting_transfer(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        Http::preventStrayRequests();

        $first = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'apisyria',
        ])->assertCreated();

        $second = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'apisyria',
        ])->assertCreated();

        $this->assertSame($first->json('data.payment_id'), $second->json('data.payment_id'));
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame(
            1,
            ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'awaiting_transfer')->count(),
        );
    }
}
