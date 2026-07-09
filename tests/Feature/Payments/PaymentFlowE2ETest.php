<?php

namespace Tests\Feature\Payments;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\InfrastructureDomain\WebhookLogStatus;
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
use App\Services\Payments\Data\GatewayInitiatePaymentData;
use App\Services\Payments\Data\GatewayRefundData;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\PaymentGatewayService;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\RefundService;
use App\Support\Payments\PaymentCorrelation;
use App\Support\Webhooks\WebhookCorrelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;
use Tests\Unit\Services\Payments\CountingShamCashGatewayStub;
use Tests\Unit\Services\Payments\DecliningPaymentGatewayStub;

/**
 * Phase 7.5 — end-to-end payment integration flows over HTTP + domain orchestration.
 *
 * @deprecated Legacy hosted-checkout E2E — superseded by Manual Transfer flow (§7.9).
 *             Rewrite scheduled for Batch 7.8; skipped until then so Batch 7.7 API wiring can ship.
 */
class PaymentFlowE2ETest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped('Legacy hosted-checkout E2E superseded by Manual Transfer flow — rewrite in Batch 7.8 (§7.9).');

        config()->set('tenancy.base_domain', 'localhost');
        $this->configureShamcashGateway();
    }

    #[Test]
    public function initiate_redirect_webhook_marks_order_paid_with_shared_correlation(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionId = 'SC-E2E-FLOW-001';
        $this->fakeSuccessfulShamcashInitiate($transactionId);

        $initiate = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
            'amount' => '120.00',
        ]);

        $initiate
            ->assertCreated()
            ->assertJsonPath('data.provider_transaction_id', $transactionId)
            ->assertJsonPath('meta.redirect_url', 'https://pay.shamcash.test/checkout/'.$transactionId);

        $initiateCorrelation = PaymentCorrelation::forProviderTransaction('shamcash', $transactionId);

        $this->assertDatabaseHas('activity_logs', [
            'correlation_id' => $initiateCorrelation,
            'action' => 'initiated',
        ]);

        $this->postSignedShamcashWebhook([
            'event_id' => $transactionId,
            'event_type' => 'payment.completed',
            'provider_transaction_id' => $transactionId,
        ])
            ->assertAccepted()
            ->assertJsonPath('data.status', WebhookLogStatus::Processed->value);

        $webhookCorrelation = WebhookCorrelation::id('shamcash', $transactionId);

        $this->assertSame($initiateCorrelation, $webhookCorrelation);
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $payment = PaymentTransaction::withoutGlobalScopes()->findOrFail($initiate->json('data.id'));
        $this->assertSame(PaymentTransactionStatus::Completed, $payment->status);

        $this->assertDatabaseHas('webhook_logs', [
            'correlation_id' => $webhookCorrelation,
            'status' => WebhookLogStatus::Processed->value,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'correlation_id' => $webhookCorrelation,
            'action' => 'completed',
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'correlation_id' => $webhookCorrelation,
            'event_type' => 'payment.completed',
        ]);
    }

    #[Test]
    public function webhook_before_redirect_still_completes_payment(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionId = 'SC-E2E-EARLY-WH';
        $this->fakeSuccessfulShamcashInitiate($transactionId);

        $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
        ])->assertCreated();

        $this->postSignedShamcashWebhook([
            'event_id' => $transactionId,
            'event_type' => 'payment.completed',
            'provider_transaction_id' => $transactionId,
        ])->assertAccepted();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    #[Test]
    public function initiate_gateway_failure_allows_retry_after_failed_payment(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            paymentGateways: ['shamcash' => new DecliningPaymentGatewayStub],
            refundGateways: ['shamcash' => new DecliningPaymentGatewayStub],
            signatureVerifiers: [],
        ));
        $this->app->forgetInstance(PaymentGatewayService::class);

        $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
        ])->assertStatus(422);

        $this->assertSame(0, PaymentTransaction::withoutGlobalScopes()->count());

        PaymentTransaction::factory()->forOrder($order)->create([
            'venue_id' => $venue->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'SC-FAILED-PREV',
            'amount' => '120.00',
            'status' => PaymentTransactionStatus::Failed,
        ]);

        $stub = new CountingShamCashGatewayStub;
        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            paymentGateways: ['shamcash' => $stub],
            refundGateways: ['shamcash' => $stub],
            signatureVerifiers: [],
        ));
        $this->app->forgetInstance(PaymentGatewayService::class);

        $result = app(PaymentGatewayService::class)->initiatePayment(new GatewayInitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
        ));

        $this->assertSame('shamcash-count-'.$order->id, $result->payment->provider_transaction_id);
        $this->assertSame(1, $stub->initiateCalls);
        $this->assertSame(2, PaymentTransaction::withoutGlobalScopes()->count());
    }

    #[Test]
    public function initiating_payment_twice_via_api_is_idempotent_for_pending_order_and_provider(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionId = 'SC-E2E-IDEM-001';
        $this->fakeSuccessfulShamcashInitiate($transactionId);

        Http::preventStrayRequests();

        $first = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
        ])->assertCreated();

        $second = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
        ])->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame($first->json('meta.redirect_url'), $second->json('meta.redirect_url'));
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame(1, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'initiated')->count());
    }

    #[Test]
    public function duplicate_webhook_does_not_apply_domain_changes_twice(): void
    {
        ['payment' => $payment, 'order' => $order] = $this->seedPendingPayment('shamcash', 'SC-DUP-WH-001');

        $payload = [
            'event_id' => 'evt_dup_e2e',
            'event_type' => 'payment.completed',
            'provider_transaction_id' => 'SC-DUP-WH-001',
        ];

        $this->postSignedShamcashWebhook($payload)->assertAccepted();
        $this->postSignedShamcashWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(
            1,
            ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'completed')->count(),
        );
        $this->assertSame(
            1,
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'order.paid')->count(),
        );
    }

    #[Test]
    public function invalid_webhook_signature_is_rejected_without_domain_changes(): void
    {
        $this->seedPendingPayment('shamcash', 'SC-BAD-SIG');

        $this->postJson('/webhooks/shamcash', [
            'event_id' => 'evt_bad_sig_e2e',
            'event_type' => 'payment.completed',
            'provider_transaction_id' => 'SC-BAD-SIG',
        ], ['X-ShamCash-Signature' => 'invalid'])->assertUnauthorized();

        $this->assertSame(OrderStatus::Pending, Order::query()->first()->status);
        $this->assertDatabaseHas('webhook_logs', [
            'provider_event_id' => 'evt_bad_sig_e2e',
            'status' => WebhookLogStatus::FailedSignature->value,
        ]);
    }

    #[Test]
    public function full_refund_flow_via_gateway_and_webhook(): void
    {
        ['owner' => $owner, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionId = 'SC-E2E-REFUND-FULL';

        $payment = PaymentTransaction::factory()->forOrder($order)->create([
            'venue_id' => $venue->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => $transactionId,
            'amount' => '120.00',
            'status' => PaymentTransactionStatus::Pending,
        ]);

        $this->postSignedShamcashWebhook([
            'event_id' => $transactionId,
            'event_type' => 'payment.completed',
            'provider_transaction_id' => $transactionId,
        ])->assertAccepted();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->bindTenant($venue->id);

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '120.00',
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));

        $providerRefundId = 'SC-REF-E2E-001';
        $this->fakeSuccessfulShamcashRefund($providerRefundId);

        app(PaymentGatewayService::class)->refund(new GatewayRefundData(
            refundId: $refund->id,
            actor: $owner,
        ));

        $this->postSignedShamcashWebhook([
            'event_id' => 'evt_refund_e2e',
            'event_type' => 'refund.processed',
            'refund_id' => $refund->id,
            'provider_refund_id' => $providerRefundId,
        ])->assertAccepted();

        $this->assertSame(RefundStatus::Processed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
    }

    #[Test]
    public function duplicate_refund_webhook_is_idempotent(): void
    {
        ['owner' => $owner, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $order->update(['status' => OrderStatus::Paid]);

        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'venue_id' => $venue->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'SC-REF-DUP',
            'amount' => '120.00',
        ]);

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '120.00',
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));

        $refund->update([
            'status' => RefundStatus::Pending,
            'provider_refund_id' => 'SC-REF-DUP-ID',
        ]);

        $payload = [
            'event_id' => 'evt_refund_dup',
            'event_type' => 'refund.processed',
            'refund_id' => $refund->id,
            'provider_refund_id' => 'SC-REF-DUP-ID',
        ];

        $this->postSignedShamcashWebhook($payload)->assertAccepted();
        $this->postSignedShamcashWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(
            1,
            ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'processed')->where('entity_type', Refund::class)->count(),
        );
    }

    #[Test]
    public function paid_order_records_single_commission_when_worker_orchestration_runs(): void
    {
        ['token' => $token, 'owner' => $owner, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        $venue->update(['commission_rate' => 5.00]);
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionId = 'SC-E2E-COMM-001';
        $this->fakeSuccessfulShamcashInitiate($transactionId);

        $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
        ])->assertCreated();

        $this->postSignedShamcashWebhook([
            'event_id' => $transactionId,
            'event_type' => 'payment.completed',
            'provider_transaction_id' => $transactionId,
        ])->assertAccepted();

        $this->bindTenant($venue->id);

        $payment = PaymentTransaction::withoutGlobalScopes()
            ->where('provider_transaction_id', $transactionId)
            ->firstOrFail();
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

        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'venue_id' => $venue->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'SC-COMM-ADJ',
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
            providerRefundId: 'SC-REF-COMM',
        ));

        $commissionService = app(CommissionService::class);

        $first = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));
        $second = $commissionService->recordAdjustment(new RecordCommissionAdjustmentData($refund->id));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CommissionAdjustment::query()->count());
        $this->assertSame('20.00', $first->adjustment_amount);
    }

    #[Test]
    public function parallel_initiate_requests_create_single_payment_and_single_audit_trail(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $stub = new CountingShamCashGatewayStub;
        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            paymentGateways: ['shamcash' => $stub],
            refundGateways: ['shamcash' => $stub],
            signatureVerifiers: [],
        ));

        $service = app(PaymentGatewayService::class);
        $request = new GatewayInitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            actor: $owner,
        );

        $first = $service->initiatePayment($request);
        $second = $service->initiatePayment($request);

        $this->assertSame(1, $stub->initiateCalls);
        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertSame($first->redirectUrl, $second->redirectUrl);
        $this->assertSame(1, PaymentTransaction::query()->count());
        $this->assertSame(1, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('action', 'initiated')->count());
        $this->assertSame(1, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->where('event_type', 'payment.initiated')->count());
    }

    /**
     * @return array{payment: PaymentTransaction, order: Order}
     */
    private function seedPendingPayment(string $provider, string $providerTransactionId): array
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $payment = PaymentTransaction::factory()->forOrder($order)->create([
            'venue_id' => $venue->id,
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'amount' => '120.00',
            'status' => PaymentTransactionStatus::Pending,
        ]);

        return ['payment' => $payment, 'order' => $order];
    }
}
