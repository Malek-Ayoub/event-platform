<?php

namespace Tests\Unit\Services\Orders;

use App\Exceptions\Orders\InsufficientTicketsException;
use App\Exceptions\Orders\ReservationAlreadyLinkedException;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Reservation;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TableSeat;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VenueTable;
use App\Models\Zone;
use App\Services\ActivityLogService;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\OrderService;
use App\Services\OutboxService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_order_with_tickets_activity_log_and_outbox_event(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 100,
            'quantity' => 5,
        ]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: '+1234567890',
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, 2)],
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('200.00', $order->total);
        $this->assertCount(2, $order->tickets);
        $this->assertSame(2, $ticketType->fresh()->quantity_sold);

        $this->assertDatabaseHas('activity_logs', [
            'venue_id' => $venue->id,
            'entity_type' => Order::class,
            'entity_id' => $order->id,
            'action' => 'created',
            'actor_user_id' => $owner->id,
        ]);

        $outbox = OutboxEvent::query()->where('aggregate_id', $order->id)->first();
        $this->assertNotNull($outbox);
        $this->assertSame('order.created', $outbox->event_type);
        $this->assertSame(Order::class, $outbox->aggregate_type);
        $this->assertSame([
            'aggregate' => 'order',
            'aggregate_id' => $order->id,
            'event' => 'order.created',
            'version' => 1,
            'payload' => [
                'order_number' => $order->order_number,
                'event_id' => $order->event_id,
                'subtotal' => $order->subtotal,
                'total' => $order->total,
            ],
        ], array_diff_key($outbox->payload, ['occurred_at' => true]));
        $this->assertArrayHasKey('occurred_at', $outbox->payload);
    }

    #[Test]
    public function it_links_reservation_to_order_when_provided(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create();

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, 1)],
            reservationId: $reservation->id,
        ));

        $this->assertSame($order->id, $reservation->fresh()->order_id);
    }

    #[Test]
    public function it_snapshots_ticket_prices_at_checkout_and_does_not_recalculate_from_ticket_type(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 100,
            'quantity' => 5,
        ]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, 2)],
        ));

        $ticketType->update(['price' => 999]);

        $this->assertSame('200.00', $order->fresh()->subtotal);
        $this->assertSame('200.00', $order->fresh()->total);
    }

    #[Test]
    public function it_rolls_back_when_reservation_is_already_linked_to_an_order(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $existingOrder = Order::factory()->forEvent($event)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create([
            'order_id' => $existingOrder->id,
        ]);

        try {
            app(OrderService::class)->createOrder(new CreateOrderData(
                eventId: $event->id,
                customerName: 'Jane Doe',
                customerEmail: 'jane@example.com',
                customerPhone: null,
                customerUserId: null,
                lineItems: [new CreateOrderLineItemData($ticketType->id, 1)],
                reservationId: $reservation->id,
            ));
            $this->fail('Expected ReservationAlreadyLinkedException');
        } catch (ReservationAlreadyLinkedException) {
            // expected
        }

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame($existingOrder->id, $reservation->fresh()->order_id);
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
        $this->assertSame(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_rolls_back_order_when_ticket_creation_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity' => 1,
            'quantity_sold' => 0,
        ]);

        try {
            app(OrderService::class)->createOrder(new CreateOrderData(
                eventId: $event->id,
                customerName: 'Jane Doe',
                customerEmail: 'jane@example.com',
                customerPhone: null,
                customerUserId: null,
                lineItems: [new CreateOrderLineItemData($ticketType->id, 5)],
            ));
            $this->fail('Expected InsufficientTicketsException');
        } catch (InsufficientTicketsException) {
            // expected
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
        $this->assertSame(0, OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_rolls_back_when_activity_log_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);

        $this->mock(ActivityLogService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('log failed'));
        });

        try {
            app(OrderService::class)->createOrder(new CreateOrderData(
                eventId: $event->id,
                customerName: 'Jane Doe',
                customerEmail: 'jane@example.com',
                customerPhone: null,
                customerUserId: null,
                lineItems: [new CreateOrderLineItemData($ticketType->id, 1)],
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Ticket::query()->count());
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        try {
            app(OrderService::class)->createOrder(new CreateOrderData(
                eventId: $event->id,
                customerName: 'Jane Doe',
                customerEmail: 'jane@example.com',
                customerPhone: null,
                customerUserId: null,
                lineItems: [new CreateOrderLineItemData($ticketType->id, 1)],
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function it_enforces_cross_tenant_isolation_for_events(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $ticketTypeB = TicketType::factory()->forEvent($eventB)->create(['quantity' => 5]);

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $eventB->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketTypeB->id, 1)],
        ));
    }

    #[Test]
    public function super_admin_cannot_create_order_for_another_tenants_event(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $admin = User::factory()->superAdmin()->create();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        $ticketTypeB = TicketType::factory()->forEvent($eventB)->create(['quantity' => 5]);

        $this->bindTenant($venueA->id);

        $this->expectException(ModelNotFoundException::class);

        app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $eventB->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketTypeB->id, 1)],
            actor: $admin,
        ));
    }
}
