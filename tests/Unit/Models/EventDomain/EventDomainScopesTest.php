<?php

namespace Tests\Unit\Models\EventDomain;

use App\Enums\EventDomain\EventStatus;
use App\Enums\EventDomain\ReservationStatus;
use App\Enums\EventDomain\SeatingUnitStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TableSeat;
use App\Models\VenueTable;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDomainScopesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function category_active_scope_filters_inactive_records(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        Category::factory()->create(['venue_id' => $venue->id, 'is_active' => true]);
        Category::factory()->inactive()->create(['venue_id' => $venue->id]);

        $this->assertCount(1, Category::query()->active()->get());
    }

    #[Test]
    public function event_status_scopes_filter_by_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        Event::factory()->create(['venue_id' => $venue->id, 'status' => EventStatus::Draft]);
        Event::factory()->published()->create(['venue_id' => $venue->id]);

        $this->assertCount(1, Event::query()->published()->get());
        $this->assertCount(1, Event::query()->draft()->get());
        $this->assertCount(1, Event::query()->withStatus(EventStatus::Published)->get());
    }

    #[Test]
    public function seating_available_scopes_filter_by_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        VenueTable::factory()->forZone($zone)->create(['status' => SeatingUnitStatus::Available]);
        VenueTable::factory()->forZone($zone)->create(['status' => SeatingUnitStatus::Reserved]);

        $table = VenueTable::query()->where('status', SeatingUnitStatus::Available)->first();
        TableSeat::factory()->forVenueTable($table)->create([
            'seat_number' => 'A1',
            'status' => SeatingUnitStatus::Available,
        ]);
        TableSeat::factory()->forVenueTable($table)->create([
            'seat_number' => 'A2',
            'status' => SeatingUnitStatus::Reserved,
        ]);

        $this->assertCount(1, VenueTable::query()->available()->get());
        $this->assertCount(1, TableSeat::query()->available()->get());
    }

    #[Test]
    public function reservation_active_scope_includes_hold_and_confirmed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();

        Reservation::factory()->forTableSeat($seat)->create(['status' => ReservationStatus::Hold]);
        Reservation::factory()->forTableSeat($seat)->create(['status' => ReservationStatus::Confirmed]);
        Reservation::factory()->forTableSeat($seat)->create(['status' => ReservationStatus::Cancelled]);

        $this->assertCount(2, Reservation::query()->active()->get());
    }
}
