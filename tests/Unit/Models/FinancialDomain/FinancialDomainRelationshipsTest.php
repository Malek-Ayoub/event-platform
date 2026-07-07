<?php

namespace Tests\Unit\Models\FinancialDomain;

use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialDomainRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_has_many_payment_transactions_for_retries_and_failures(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $failedAttempt = PaymentTransaction::factory()->forOrder($order)->failed()->create();
        $completedAttempt = PaymentTransaction::factory()->forOrder($order)->completed()->create();

        $this->assertCount(2, $order->fresh()->paymentTransactions);
        $this->assertTrue($order->paymentTransactions->contains($failedAttempt));
        $this->assertTrue($order->paymentTransactions->contains($completedAttempt));
    }

    #[Test]
    public function order_has_many_refunds_and_has_one_commission(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $refundA = Refund::factory()->forOrder($order)->create();
        $refundB = Refund::factory()->forOrder($order)->create();
        $commission = Commission::factory()->forOrder($order)->create();

        $this->assertCount(2, $order->fresh()->refunds);
        $this->assertTrue($order->refunds->contains($refundA));
        $this->assertTrue($order->refunds->contains($refundB));
        $this->assertTrue($order->fresh()->commission->is($commission));
    }

    #[Test]
    public function refund_can_be_linked_to_payment_transaction(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $transaction = PaymentTransaction::factory()->forOrder($order)->completed()->create();
        $refund = Refund::factory()->forPaymentTransaction($transaction)->create();

        $this->assertSame($transaction->id, $refund->payment_transaction_id);
        $this->assertTrue($refund->paymentTransaction->is($transaction));
        $this->assertTrue($refund->order->is($order));
        $this->assertTrue($transaction->refunds->contains($refund));
    }

    #[Test]
    public function refund_can_exist_without_payment_transaction_for_administrative_refunds(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $refund = Refund::factory()->forOrder($order)->withoutPaymentTransaction()->create();

        $this->assertNull($refund->payment_transaction_id);
        $this->assertNull($refund->paymentTransaction);
        $this->assertTrue($refund->order->is($order));
    }

    #[Test]
    public function commission_adjustment_links_commission_and_refund(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $commission = Commission::factory()->forOrder($order)->create();
        $refund = Refund::factory()->forOrder($order)->create();
        $adjustment = CommissionAdjustment::factory()->forCommissionAndRefund($commission, $refund)->create();

        $this->assertTrue($adjustment->commission->is($commission));
        $this->assertTrue($adjustment->refund->is($refund));
        $this->assertTrue($commission->adjustments->contains($adjustment));
        $this->assertTrue($refund->commissionAdjustment->is($adjustment));
    }

    #[Test]
    public function relation_methods_return_typed_relation_objects(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $order = Order::factory()->create(['venue_id' => $venue->id]);

        $this->assertInstanceOf(HasMany::class, $order->paymentTransactions());
        $this->assertInstanceOf(HasMany::class, $order->refunds());
        $this->assertInstanceOf(HasOne::class, $order->commission());
        $this->assertInstanceOf(BelongsTo::class, (new PaymentTransaction)->order());
        $this->assertInstanceOf(HasMany::class, (new PaymentTransaction)->refunds());
        $this->assertInstanceOf(BelongsTo::class, (new Refund)->paymentTransaction());
        $this->assertInstanceOf(HasOne::class, (new Refund)->commissionAdjustment());
        $this->assertInstanceOf(HasMany::class, (new Commission)->adjustments());
    }

    #[Test]
    public function tenant_scope_filters_financial_domain_models(): void
    {
        $venueA = Venue::factory()->create();
        $venueB = Venue::factory()->create();

        $this->bindTenant($venueA->id);
        $eventA = Event::factory()->create(['venue_id' => $venueA->id]);
        $orderA = Order::factory()->forEvent($eventA)->create();
        PaymentTransaction::factory()->forOrder($orderA)->create();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $orderB = Order::factory()->forEvent($eventB)->create();
        PaymentTransaction::factory()->forOrder($orderB)->create();

        $this->bindTenant($venueA->id);

        $this->assertCount(1, PaymentTransaction::query()->get());
        $this->assertCount(
            2,
            PaymentTransaction::query()->withoutGlobalScope(BelongsToVenueScope::class)->get(),
        );
    }
}
