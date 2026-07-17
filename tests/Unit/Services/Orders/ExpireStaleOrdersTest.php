<?php

namespace Tests\Unit\Services\Orders;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TicketType;
use App\Models\Venue;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\OrderService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class ExpireStaleOrdersTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    /**
     * @return array{venue: Venue, event: Event, ticketType: TicketType, order: Order}
     */
    private function createPendingOrderWithReservedInventory(
        int $quantity = 2,
        ?CarbonInterface $createdAt = null,
    ): array {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 40,
            'quantity' => 20,
            'quantity_sold' => 0,
        ]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Stale Guest',
            customerEmail: 'stale@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, $quantity)],
        ));

        if ($createdAt !== null) {
            Order::query()->whereKey($order->id)->update(['created_at' => $createdAt]);
            $order->refresh();
        }

        return [
            'venue' => $venue,
            'event' => $event,
            'ticketType' => $ticketType->fresh(),
            'order' => $order->fresh(['orderItems']),
        ];
    }

    #[Test]
    public function it_expires_stale_pending_orders_and_releases_inventory(): void
    {
        [
            'ticketType' => $ticketType,
            'order' => $order,
        ] = $this->createPendingOrderWithReservedInventory(
            quantity: 3,
            createdAt: now()->subMinutes(45),
        );

        $this->assertSame(3, $ticketType->quantity_sold);
        $this->assertSame(OrderStatus::Pending, $order->status);

        $expired = app(OrderService::class)->expireStalePendingOrders(30);

        $this->assertSame(1, $expired);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->assertDatabaseHas('activity_logs', [
            'entity_type' => Order::class,
            'entity_id' => $order->id,
            'action' => 'expired_stale',
            'actor_user_id' => null,
        ]);

        $outbox = OutboxEvent::query()
            ->where('aggregate_id', $order->id)
            ->where('event_type', 'order.expired')
            ->first();
        $this->assertNotNull($outbox);
    }

    #[Test]
    public function it_does_not_expire_recent_pending_orders(): void
    {
        [
            'ticketType' => $ticketType,
            'order' => $order,
        ] = $this->createPendingOrderWithReservedInventory(
            quantity: 2,
            createdAt: now()->subMinutes(10),
        );

        $expired = app(OrderService::class)->expireStalePendingOrders(30);

        $this->assertSame(0, $expired);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(2, $ticketType->fresh()->quantity_sold);
    }

    #[Test]
    public function it_skips_pending_orders_that_have_a_payment_transaction(): void
    {
        [
            'ticketType' => $ticketType,
            'order' => $order,
        ] = $this->createPendingOrderWithReservedInventory(
            quantity: 2,
            createdAt: now()->subMinutes(60),
        );

        PaymentTransaction::factory()->forOrder($order)->awaitingTransfer()->create([
            'amount' => $order->total,
        ]);

        $expired = app(OrderService::class)->expireStalePendingOrders(30);

        $this->assertSame(0, $expired);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(2, $ticketType->fresh()->quantity_sold);
    }

    #[Test]
    public function it_does_not_touch_non_pending_orders(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity' => 10,
            'quantity_sold' => 4,
        ]);

        $paid = Order::factory()->forEvent($event)->create([
            'status' => OrderStatus::Paid,
            'created_at' => now()->subHours(2),
        ]);
        OrderItem::query()->create([
            'venue_id' => $venue->id,
            'order_id' => $paid->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => '10.00',
        ]);

        $cancelled = Order::factory()->forEvent($event)->create([
            'status' => OrderStatus::Cancelled,
            'created_at' => now()->subHours(2),
        ]);
        $failed = Order::factory()->forEvent($event)->create([
            'status' => OrderStatus::Failed,
            'created_at' => now()->subHours(2),
        ]);

        $expired = app(OrderService::class)->expireStalePendingOrders(30);

        $this->assertSame(0, $expired);
        $this->assertSame(OrderStatus::Paid, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $cancelled->fresh()->status);
        $this->assertSame(OrderStatus::Failed, $failed->fresh()->status);
        $this->assertSame(4, $ticketType->fresh()->quantity_sold);
    }

    #[Test]
    public function it_does_not_expire_orders_belonging_to_another_tenant_when_tenant_is_bound(): void
    {
        [
            'venue' => $venueA,
            'ticketType' => $ticketTypeA,
            'order' => $orderA,
        ] = $this->createPendingOrderWithReservedInventory(
            quantity: 1,
            createdAt: now()->subMinutes(60),
        );

        ['venue' => $venueB] = $this->createVenueOwner();
        $this->bindTenant($venueB->id);

        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $this->attachDefaultPaymentAccount($eventB);
        $ticketTypeB = TicketType::factory()->forEvent($eventB)->create([
            'quantity' => 10,
            'quantity_sold' => 0,
        ]);
        $orderB = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $eventB->id,
            customerName: 'Other Venue',
            customerEmail: 'other@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketTypeB->id, 1)],
        ));
        Order::query()->whereKey($orderB->id)->update(['created_at' => now()->subMinutes(60)]);

        // Bound to venue B — should only expire venue B's stale order.
        $expired = app(OrderService::class)->expireStalePendingOrders(30);

        $this->assertSame(1, $expired);
        $this->assertSame(OrderStatus::Cancelled, $orderB->fresh()->status);
        $this->assertSame(0, $ticketTypeB->fresh()->quantity_sold);

        $this->bindTenant($venueA->id);
        $this->assertSame(OrderStatus::Pending, $orderA->fresh()->status);
        $this->assertSame(1, $ticketTypeA->fresh()->quantity_sold);
    }

    #[Test]
    public function console_command_expires_stale_orders_and_prints_result(): void
    {
        $this->createPendingOrderWithReservedInventory(
            quantity: 1,
            createdAt: now()->subMinutes(60),
        );

        // Console runs without a tenant binding — processes all venues.
        app(TenantContextInterface::class)->clear();

        $this->artisan('orders:expire-stale', ['--minutes' => 30, '--limit' => 100])
            ->expectsOutputToContain('Stale orders: expired=1')
            ->assertSuccessful();

        $this->assertSame(
            1,
            Order::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('status', OrderStatus::Cancelled)
                ->count(),
        );
        $this->assertSame(
            1,
            ActivityLog::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('action', 'expired_stale')
                ->count(),
        );
    }
}
