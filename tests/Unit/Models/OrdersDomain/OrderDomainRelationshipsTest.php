<?php

namespace Tests\Unit\Models\OrdersDomain;

use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TableSeat;
use App\Models\Ticket;
use App\Models\TicketSerialCounter;
use App\Models\TicketType;
use App\Models\Venue;
use App\Models\VenueTable;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDomainRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_belongs_to_venue_event_and_has_tickets(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create();

        $this->assertTrue($order->venue->is($venue));
        $this->assertTrue($order->event->is($event));
        $this->assertTrue($order->tickets->contains($ticket));
        $this->assertTrue($event->orders->contains($order));
        $this->assertTrue($event->tickets->contains($ticket));
        $this->assertTrue($venue->fresh()->orders->contains($order));
    }

    #[Test]
    public function ticket_belongs_to_order_ticket_type_and_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create();

        $this->assertTrue($ticket->order->is($order));
        $this->assertTrue($ticket->ticketType->is($ticketType));
        $this->assertTrue($ticket->event->is($event));
        $this->assertTrue($ticketType->tickets->contains($ticket));
    }

    #[Test]
    public function ticket_serial_counter_belongs_to_venue_and_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $counter = TicketSerialCounter::factory()->forEvent($event)->create();

        $this->assertTrue($counter->venue->is($venue));
        $this->assertTrue($counter->event->is($event));
        $this->assertTrue($venue->fresh()->ticketSerialCounters->contains($counter));
    }

    #[Test]
    public function reservation_belongs_to_order(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create([
            'order_id' => $order->id,
        ]);

        $this->assertTrue($reservation->order->is($order));
        $this->assertTrue($order->reservations->contains($reservation));
    }

    #[Test]
    public function relation_methods_return_typed_relation_objects(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $order = Order::factory()->create(['venue_id' => $venue->id]);

        $this->assertInstanceOf(BelongsTo::class, $order->venue());
        $this->assertInstanceOf(BelongsTo::class, $order->event());
        $this->assertInstanceOf(HasMany::class, $order->tickets());
        $this->assertInstanceOf(BelongsTo::class, (new Ticket)->order());
    }

    #[Test]
    public function tenant_scope_filters_order_domain_models(): void
    {
        $venueA = Venue::factory()->create();
        $venueB = Venue::factory()->create();

        $this->bindTenant($venueA->id);
        $eventA = Event::factory()->create(['venue_id' => $venueA->id]);
        Order::factory()->forEvent($eventA)->create();

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);
        Order::factory()->forEvent($eventB)->create();

        $this->bindTenant($venueA->id);

        $this->assertCount(1, Order::query()->get());
        $this->assertCount(
            2,
            Order::query()->withoutGlobalScope(BelongsToVenueScope::class)->get(),
        );
    }
}
