<?php

namespace Tests\Unit\Models\EventDomain;

use App\Enums\EventDomain\EventStatus;
use App\Enums\EventDomain\ReservationStatus;
use App\Enums\EventDomain\SeatingUnitStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TableSeat;
use App\Models\TicketType;
use App\Models\VenueTable;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDomainCastsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function category_casts_boolean_and_integer_fields(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $category = Category::factory()->create([
            'venue_id' => $venue->id,
            'is_active' => 1,
            'sort_order' => '5',
        ]);

        $this->assertIsBool($category->is_active);
        $this->assertTrue($category->is_active);
        $this->assertSame(5, $category->sort_order);
    }

    #[Test]
    public function event_casts_status_enum_and_datetime_fields(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->published()->create(['venue_id' => $venue->id]);

        $this->assertInstanceOf(EventStatus::class, $event->status);
        $this->assertSame(EventStatus::Published, $event->status);
        $this->assertInstanceOf(Carbon::class, $event->start_datetime);
        $this->assertNull($event->gallery);
    }

    #[Test]
    public function ticket_type_casts_decimal_and_datetime_fields(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => '99.50',
        ]);

        $this->assertSame('99.50', $ticketType->price);
        $this->assertInstanceOf(Carbon::class, $ticketType->sale_start);
    }

    #[Test]
    public function seating_models_cast_status_enums(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create([
            'status' => SeatingUnitStatus::Reserved,
        ]);
        $seat = TableSeat::factory()->forVenueTable($table)->create([
            'status' => SeatingUnitStatus::Unavailable,
        ]);

        $this->assertSame(SeatingUnitStatus::Reserved, $table->status);
        $this->assertSame(SeatingUnitStatus::Unavailable, $seat->status);
    }

    #[Test]
    public function reservation_casts_status_enum_and_held_until(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create([
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertInstanceOf(Carbon::class, $reservation->held_until);
    }
}
