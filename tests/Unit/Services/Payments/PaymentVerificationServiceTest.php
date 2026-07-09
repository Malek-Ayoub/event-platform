<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\Payments\GatewayOutcome;
use App\Enums\Payments\VerificationFailureReason;
use App\Exceptions\Payments\DuplicateTransactionNumberException;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\VerifyTransactionData;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\PaymentVerificationService;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment_gateways.providers.apisyria.merchant_account' => 'WALLET-001',
        ]);

        $this->swapVerificationGateway(new ApiSyriaVerificationGatewayStub);
    }

    #[Test]
    public function it_marks_payment_paid_when_gateway_lookup_matches(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->createAwaitingPayment($venue->id, '100.00');

        $result = app(PaymentVerificationService::class)->verify(new VerifyTransactionData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-1001',
            actor: $owner,
        ));

        $this->assertSame(PaymentTransactionStatus::Paid, $result->status);
        $this->assertSame('APISYRIA-TX-1001', $result->provider_transaction_id);
        $this->assertSame(OrderStatus::Paid, $payment->order->fresh()->status);
    }

    #[Test]
    public function it_rejects_duplicate_transaction_number_before_calling_gateway(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $firstPayment = $this->createAwaitingPayment($venue->id, '100.00');
        $secondPayment = $this->createAwaitingPayment($venue->id, '100.00', order: Order::factory()->forEvent(
            Event::factory()->create(['venue_id' => $venue->id]),
        )->create([
            'venue_id' => $venue->id,
            'total' => '100.00',
            'subtotal' => '100.00',
            'status' => OrderStatus::Pending,
        ]));

        app(PaymentVerificationService::class)->verify(new VerifyTransactionData(
            paymentTransactionId: $firstPayment->id,
            transactionNumber: 'TX-DUP-001',
            actor: $owner,
        ));

        $this->expectException(DuplicateTransactionNumberException::class);

        app(PaymentVerificationService::class)->verify(new VerifyTransactionData(
            paymentTransactionId: $secondPayment->id,
            transactionNumber: 'TX-DUP-001',
            actor: $owner,
        ));
    }

    #[Test]
    public function it_marks_payment_failed_when_gateway_amount_does_not_match(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $this->swapVerificationGateway(new ApiSyriaVerificationGatewayStub(
            response: new VerifyTransactionResponse(
                outcome: GatewayOutcome::Success,
                found: true,
                amount: '50.00',
                currency: 'USD',
                receiverAccount: 'WALLET-001',
                providerTransactionId: 'APISYRIA-MISMATCH',
            ),
        ));

        $payment = $this->createAwaitingPayment($venue->id, '100.00');

        $result = app(PaymentVerificationService::class)->verify(new VerifyTransactionData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-MISMATCH',
        ));

        $this->assertSame(PaymentTransactionStatus::Failed, $result->status);
        $this->assertSame(OrderStatus::Pending, $payment->order->fresh()->status);
    }

    #[Test]
    public function it_expires_payment_when_instruction_ttl_has_passed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $payment = $this->createAwaitingPayment($venue->id, '100.00', expired: true);

        $result = app(PaymentVerificationService::class)->verify(new VerifyTransactionData(
            paymentTransactionId: $payment->id,
            transactionNumber: 'TX-EXPIRED',
        ));

        $this->assertSame(PaymentTransactionStatus::Expired, $result->status);
    }

    private function createAwaitingPayment(
        int $venueId,
        string $amount,
        ?Order $order = null,
        bool $expired = false,
    ): PaymentTransaction {
        $event = Event::factory()->create(['venue_id' => $venueId]);
        $order ??= Order::factory()->forEvent($event)->create([
            'venue_id' => $venueId,
            'total' => $amount,
            'subtotal' => $amount,
            'status' => OrderStatus::Pending,
        ]);

        return PaymentTransaction::factory()->forOrder($order)->awaitingTransfer()->create([
            'venue_id' => $venueId,
            'provider' => 'apisyria',
            'amount' => $amount,
            'currency' => 'USD',
            'expires_at' => $expired ? now()->subHour() : now()->addHour(),
        ]);
    }

    private function swapVerificationGateway(ApiSyriaVerificationGatewayStub $stub): void
    {
        $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry(
            paymentGateways: [],
            refundGateways: [],
            signatureVerifiers: [],
            verificationGateways: [
                $stub->provider() => $stub,
            ],
        ));
    }
}
