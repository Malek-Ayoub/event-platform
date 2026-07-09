<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Payments\Gateway\GatewayOperationException;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\Payments\Data\GatewayInitiatePaymentData;
use App\Services\Payments\Data\GatewayRefundData;
use App\Services\Payments\Data\GatewayVerifyTransactionData;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Stubs\ShamCashGatewayStub;
use App\Services\Payments\Gateway\Stubs\SyriatelCashGatewayStub;
use App\Services\Payments\PaymentGatewayService;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\RefundService;
use App\Support\Payments\PaymentCorrelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function initiate_payment_calls_gateway_then_persists_domain_transaction(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '150.00',
            'subtotal' => '150.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->swapRegistryWithStub(new ShamCashGatewayStub);

        $service = app(PaymentGatewayService::class);

        $result = $service->initiatePayment(new GatewayInitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame(PaymentTransactionStatus::Pending, $result->payment->status);
        $this->assertSame('shamcash-stub-'.$order->id, $result->payment->provider_transaction_id);
        $this->assertNull($result->redirectUrl);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => PaymentTransaction::class,
            'entity_id' => $result->payment->id,
            'action' => 'initiated',
        ]);
    }

    #[Test]
    public function initiate_payment_is_idempotent_for_pending_order_and_provider(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '150.00',
            'subtotal' => '150.00',
            'status' => OrderStatus::Pending,
        ]);

        $stub = new CountingShamCashGatewayStub;
        $this->swapRegistryWithStub($stub);

        $service = app(PaymentGatewayService::class);
        $request = new GatewayInitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
        );

        $first = $service->initiatePayment($request);
        $second = $service->initiatePayment($request);

        $this->assertSame(1, $stub->initiateCalls);
        $this->assertSame($first->payment->id, $second->payment->id);
        $this->assertSame('https://pay.example.test/shamcash-count-'.$order->id, $second->redirectUrl);
        $this->assertSame(1, PaymentTransaction::query()->count());
    }

    #[Test]
    public function initiate_payment_retries_gateway_after_previous_failure(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        PaymentTransaction::factory()->forOrder($order)->create([
            'venue_id' => $venue->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'TXN-FAILED-1',
            'amount' => '100.00',
            'status' => PaymentTransactionStatus::Failed,
        ]);

        $stub = new CountingShamCashGatewayStub;
        $this->swapRegistryWithStub($stub);

        $result = app(PaymentGatewayService::class)->initiatePayment(new GatewayInitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
        ));

        $this->assertSame(1, $stub->initiateCalls);
        $this->assertSame('shamcash-count-'.$order->id, $result->payment->provider_transaction_id);
        $this->assertSame(2, PaymentTransaction::query()->count());
    }

    #[Test]
    public function initiate_payment_sets_correlation_id_on_audit_records(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '90.00',
            'subtotal' => '90.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->swapRegistryWithStub(new ShamCashGatewayStub);

        $result = app(PaymentGatewayService::class)->initiatePayment(new GatewayInitiatePaymentData(
            orderId: $order->id,
            provider: 'shamcash',
            actor: $owner,
        ));

        $correlationId = PaymentCorrelation::forProviderTransaction('shamcash', $result->payment->provider_transaction_id);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => PaymentTransaction::class,
            'entity_id' => $result->payment->id,
            'correlation_id' => $correlationId,
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'correlation_id' => $correlationId,
        ]);
    }

    #[Test]
    public function initiate_payment_surfaces_gateway_decline_without_domain_side_effects(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]);

        $this->swapRegistryWithStub(new DecliningPaymentGatewayStub);

        $this->expectException(GatewayOperationException::class);

        try {
            app(PaymentGatewayService::class)->initiatePayment(new GatewayInitiatePaymentData(
                orderId: $order->id,
                provider: 'shamcash',
            ));
        } finally {
            $this->assertSame(0, PaymentTransaction::query()->count());
        }
    }

    #[Test]
    public function refund_submits_pending_refund_when_gateway_returns_async_status(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '80.00',
            'subtotal' => '80.00',
            'status' => OrderStatus::Paid,
        ]);

        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'venue_id' => $venue->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'TXN-REF-1',
            'amount' => '80.00',
        ]);

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '40.00',
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));

        $this->swapRegistryWithStub(new ShamCashGatewayStub);

        $processed = app(PaymentGatewayService::class)->refund(new GatewayRefundData(
            refundId: $refund->id,
            actor: $owner,
        ));

        $this->assertSame(RefundStatus::Pending, $processed->status);
        $this->assertSame('shamcash-refund-stub-TXN-REF-1', $processed->provider_refund_id);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => Refund::class,
            'entity_id' => $refund->id,
            'action' => 'submitted',
        ]);
    }

    #[Test]
    public function refund_processes_refund_when_gateway_returns_immediate_completion(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '80.00',
            'subtotal' => '80.00',
            'status' => OrderStatus::Paid,
        ]);

        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'venue_id' => $venue->id,
            'provider' => 'syriatel_cash',
            'provider_transaction_id' => 'SY-TXN-1',
            'amount' => '80.00',
        ]);

        $refund = app(RefundService::class)->createRefund(new CreateRefundData(
            orderId: $order->id,
            amount: '80.00',
            paymentTransactionId: $payment->id,
            actor: $owner,
        ));

        $this->swapRegistryWithStub(new ImmediateRefundGatewayStub);

        $processed = app(PaymentGatewayService::class)->refund(new GatewayRefundData(
            refundId: $refund->id,
            actor: $owner,
        ));

        $this->assertSame(RefundStatus::Processed, $processed->status);
        $this->assertSame(OrderStatus::Refunded, $order->fresh()->status);
    }

    #[Test]
    public function verify_transaction_maps_gateway_lookup_to_domain_result(): void
    {
        config(['payment_gateways.providers.apisyria.merchant_account' => 'WALLET-001']);

        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            paymentGateways: [],
            refundGateways: [],
            signatureVerifiers: [],
            verificationGateways: [
                'apisyria' => new ApiSyriaVerificationGatewayStub,
            ],
        ));

        $result = app(PaymentGatewayService::class)->verifyTransaction(new GatewayVerifyTransactionData(
            provider: 'apisyria',
            transactionNumber: 'TX-VERIFY-1',
            expectedAmount: '50.00',
            expectedCurrency: 'USD',
            merchantAccount: 'WALLET-001',
        ));

        $this->assertTrue($result->matched);
        $this->assertSame('APISYRIA-TX-VERIFY-1', $result->providerTransactionId);
    }

    private function swapRegistryWithStub(
        ShamCashGatewayStub|SyriatelCashGatewayStub|DecliningPaymentGatewayStub|ImmediateRefundGatewayStub|CountingShamCashGatewayStub $stub,
    ): void {
        $provider = $stub->provider();

        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            paymentGateways: [$provider => $stub],
            refundGateways: [$provider => $stub],
            signatureVerifiers: [],
        ));
    }
}
