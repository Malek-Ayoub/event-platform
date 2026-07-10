<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\EventPaymentAccount;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentTransaction;
use App\Services\Payments\Data\CreatePaymentInstructionsData;
use App\Services\Payments\PaymentInstructionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentInstructionServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_awaiting_transfer_payment_and_returns_instructions_without_gateway_calls(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-001')->create();
        EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($account)->create();

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $account->id,
            'total' => '120.00',
            'subtotal' => '120.00',
            'status' => OrderStatus::Pending,
        ]);

        $instructions = app(PaymentInstructionService::class)->createInstructions(new CreatePaymentInstructionsData(
            orderId: $order->id,
            provider: 'apisyria',
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame('apisyria', $instructions->provider);
        $this->assertSame($account->account_identifier, $instructions->merchantAccount);
        $this->assertSame('120.00', $instructions->amount);
        $this->assertSame($account->id, $instructions->paymentAccountId);

        $payment = PaymentTransaction::query()->findOrFail($instructions->paymentId);
        $this->assertSame(PaymentTransactionStatus::AwaitingTransfer, $payment->status);
        $this->assertSame($account->id, $payment->payment_account_id);
        $this->assertNull($payment->provider_transaction_id);
        $this->assertNotNull($payment->expires_at);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => PaymentTransaction::class,
            'entity_id' => $payment->id,
            'action' => 'awaiting_transfer',
        ]);
    }

    #[Test]
    public function it_is_idempotent_for_active_awaiting_transfer_per_order_and_provider(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $account = PaymentAccount::factory()->forVenue($venue)->shamcash('WALLET-001')->create();
        EventPaymentAccount::factory()->forEvent($event)->forPaymentAccount($account)->create();

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $account->id,
            'total' => '75.00',
            'subtotal' => '75.00',
            'status' => OrderStatus::Pending,
        ]);

        $service = app(PaymentInstructionService::class);
        $data = new CreatePaymentInstructionsData(
            orderId: $order->id,
            provider: 'apisyria',
        );

        $first = $service->createInstructions($data);
        $second = $service->createInstructions($data);

        $this->assertSame($first->paymentId, $second->paymentId);
        $this->assertSame(1, PaymentTransaction::query()->count());
    }
}
