<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\Payments\Data\GatewayRefundData;
use App\Services\Payments\Data\GatewayVerifyTransactionData;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\PaymentGatewayService;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentGatewayServiceTest extends TestCase
{
    use RefreshDatabase;

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

        $payment = PaymentTransaction::factory()->forOrder($order)->paid()->create([
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

        $this->swapRefundGateway(new ShamCashRefundGatewayStub);

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

        $payment = PaymentTransaction::factory()->forOrder($order)->paid()->create([
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

        $this->swapRefundGateway(new ImmediateRefundGatewayStub);

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
        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            refundGateways: [],
            verificationGateways: [
                'apisyria' => new ApiSyriaVerificationGatewayStub,
            ],
        ));

        $result = app(PaymentGatewayService::class)->verifyTransaction(new GatewayVerifyTransactionData(
            provider: 'apisyria',
            transactionNumber: 'TX-VERIFY-1',
            expectedAmount: '50.00',
            expectedCurrency: 'USD',
            paymentAccount: ApiSyriaVerificationGatewayStub::shamcashAccount('WALLET-001'),
        ));

        $this->assertTrue($result->matched);
        $this->assertSame('APISYRIA-TX-VERIFY-1', $result->providerTransactionId);
    }

    private function swapRefundGateway(ShamCashRefundGatewayStub|ImmediateRefundGatewayStub $stub): void
    {
        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            refundGateways: [$stub->provider() => $stub],
        ));
    }
}
