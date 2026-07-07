<?php

namespace Tests\Unit\Policies\EventDomain;

use App\Models\Category;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TableSeat;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueTable;
use App\Models\Zone;
use App\Policies\CategoryPolicy;
use App\Policies\EventPolicy;
use App\Policies\ReservationPolicy;
use App\Policies\TicketTypePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class EventDomainPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function super_admin_can_manage_all_event_domain_resources(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fixtures = $this->createEventDomainFixtures();

        $this->assertTrue(app(CategoryPolicy::class)->view($admin, $fixtures['category']));
        $this->assertTrue(app(CategoryPolicy::class)->update($admin, $fixtures['category']));
        $this->assertTrue(app(EventPolicy::class)->update($admin, $fixtures['event']));
        $this->assertTrue(app(TicketTypePolicy::class)->update($admin, $fixtures['ticketType']));
        $this->assertTrue(app(ReservationPolicy::class)->update($admin, $fixtures['reservation']));
    }

    #[Test]
    public function owner_can_manage_event_domain_resources_in_own_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $fixtures = $this->createEventDomainFixtures($venue);

        $this->assertTrue(app(CategoryPolicy::class)->create($owner));
        $this->assertTrue(app(CategoryPolicy::class)->view($owner, $fixtures['category']));
        $this->assertTrue(app(CategoryPolicy::class)->update($owner, $fixtures['category']));
        $this->assertTrue(app(EventPolicy::class)->update($owner, $fixtures['event']));
        $this->assertTrue(app(TicketTypePolicy::class)->update($owner, $fixtures['ticketType']));
        $this->assertTrue(app(ReservationPolicy::class)->update($owner, $fixtures['reservation']));
    }

    #[Test]
    public function staff_can_view_but_cannot_manage_events_or_ticket_types(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $this->bindTenant($venue->id);
        $fixtures = $this->createEventDomainFixtures($venue);

        $this->assertTrue(app(EventPolicy::class)->view($staff, $fixtures['event']));
        $this->assertFalse(app(EventPolicy::class)->update($staff, $fixtures['event']));
        $this->assertFalse(app(TicketTypePolicy::class)->update($staff, $fixtures['ticketType']));
        $this->assertFalse(app(CategoryPolicy::class)->update($staff, $fixtures['category']));
    }

    #[Test]
    public function staff_can_manage_reservations_with_default_permission(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $this->bindTenant($venue->id);
        $fixtures = $this->createEventDomainFixtures($venue);

        $this->assertTrue(app(ReservationPolicy::class)->view($staff, $fixtures['reservation']));
        $this->assertTrue(app(ReservationPolicy::class)->update($staff, $fixtures['reservation']));
    }

    #[Test]
    public function customer_cannot_access_tenant_event_domain_resources(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $this->bindTenant($venue->id);
        $fixtures = $this->createEventDomainFixtures($venue);

        $this->assertFalse(app(CategoryPolicy::class)->view($customer, $fixtures['category']));
        $this->assertFalse(app(CategoryPolicy::class)->create($customer));
        $this->assertFalse(app(EventPolicy::class)->view($customer, $fixtures['event']));
        $this->assertFalse(app(TicketTypePolicy::class)->view($customer, $fixtures['ticketType']));
        $this->assertFalse(app(ReservationPolicy::class)->view($customer, $fixtures['reservation']));
    }

    #[Test]
    public function owner_cannot_manage_resources_from_another_tenant(): void
    {
        ['user' => $ownerA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $this->bindTenant($venueB->id);
        $fixturesB = $this->createEventDomainFixtures($venueB);

        $this->assertFalse(app(EventPolicy::class)->view($ownerA, $fixturesB['event']));
        $this->assertFalse(app(EventPolicy::class)->update($ownerA, $fixturesB['event']));
        $this->assertFalse(app(ReservationPolicy::class)->update($ownerA, $fixturesB['reservation']));
    }

    /**
     * @return array{
     *     category: Category,
     *     event: Event,
     *     ticketType: TicketType,
     *     reservation: Reservation
     * }
     */
    private function createEventDomainFixtures(?Venue $venue = null): array
    {
        if ($venue === null) {
            ['venue' => $venue] = $this->createVenueOwner();
            $this->bindTenant($venue->id);
        }

        $category = Category::factory()->create(['venue_id' => $venue->id]);
        $event = Event::factory()->forCategory($category)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $zone = Zone::factory()->forEvent($event)->create();
        $table = VenueTable::factory()->forZone($zone)->create();
        $seat = TableSeat::factory()->forVenueTable($table)->create();
        $reservation = Reservation::factory()->forTableSeat($seat)->create();

        return [
            'category' => $category,
            'event' => $event,
            'ticketType' => $ticketType,
            'reservation' => $reservation,
        ];
    }
}
