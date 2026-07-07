<?php

namespace Tests\Unit\Models\EventDomain;

use App\Models\Category;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TableSeat;
use App\Models\TicketType;
use App\Models\Venue;
use App\Models\VenueTable;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDomainRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function category_belongs_to_venue_and_has_many_events(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $category = Category::factory()->create(['venue_id' => $venue->id]);
        $event = Event::factory()->forCategory($category)->create();

        $this->assertTrue($category->venue->is($venue));
        $this->assertCount(1, $category->events);
        $this->assertTrue($category->events->first()->is($event));
        $this->assertCount(1, $venue->fresh()->categories);
        $this->assertCount(1, $venue->fresh()->events);
    }

    #[Test]
    public function event_has_ticket_types_and_zones(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $zone = Zone::factory()->forEvent($event)->create();

        $this->assertTrue($event->venue->is($venue));
        $this->assertTrue($event->category()->exists());
        $this->assertTrue($event->ticketTypes->contains($ticketType));
        $this->assertTrue($event->zones->contains($zone));
    }

    #[Test]
    public function zone_has_venue_tables(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();

        $this->assertTrue($zone->event->is($event));
        $this->assertTrue($zone->venueTables->contains($table));
    }

    #[Test]
    public function venue_table_has_seats_and_reservations_through_seats(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create();

        $this->assertTrue($table->zone->is($zone));
        $this->assertTrue($table->event->is($event));
        $this->assertTrue($table->tableSeats->contains($seat));
        $this->assertTrue($table->reservations->contains($reservation));
    }

    #[Test]
    public function table_seat_has_reservations(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create();

        $this->assertTrue($seat->venueTable->is($table));
        $this->assertTrue($seat->reservations->contains($reservation));
    }

    #[Test]
    public function reservation_belongs_to_table_seat_and_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create();

        $this->assertTrue($reservation->tableSeat->is($seat));
        $this->assertTrue($reservation->venue->is($venue));
    }

    #[Test]
    public function tenant_scope_filters_event_domain_models(): void
    {
        $venueA = Venue::factory()->create();
        $venueB = Venue::factory()->create();

        Category::factory()->create(['venue_id' => $venueA->id]);
        Category::factory()->create(['venue_id' => $venueB->id]);

        $this->bindTenant($venueA->id);

        $this->assertCount(1, Category::query()->get());
        $this->assertSame($venueA->id, Category::query()->value('venue_id'));
    }

    #[Test]
    public function models_can_be_queried_without_tenant_scope_when_needed(): void
    {
        $venueA = Venue::factory()->create();
        $venueB = Venue::factory()->create();

        Category::factory()->create(['venue_id' => $venueA->id]);
        Category::factory()->create(['venue_id' => $venueB->id]);

        $this->assertCount(
            2,
            Category::query()->withoutGlobalScope(BelongsToVenueScope::class)->get(),
        );
    }
}
