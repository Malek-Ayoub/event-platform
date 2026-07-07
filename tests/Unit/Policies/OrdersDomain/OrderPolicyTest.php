<?php

namespace Tests\Unit\Policies\OrdersDomain;

use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\Venue;
use App\Policies\OrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function super_admin_can_manage_orders(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $order = $this->createOrderFixture();

        $this->assertTrue(app(OrderPolicy::class)->update($admin, $order));
    }

    #[Test]
    public function owner_can_manage_orders_in_own_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $order = $this->createOrderFixture($venue);

        $this->assertTrue(app(OrderPolicy::class)->create($owner));
        $this->assertTrue(app(OrderPolicy::class)->update($owner, $order));
    }

    #[Test]
    public function staff_can_manage_orders_with_default_permission(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $this->bindTenant($venue->id);
        $order = $this->createOrderFixture($venue);

        $this->assertTrue(app(OrderPolicy::class)->view($staff, $order));
        $this->assertTrue(app(OrderPolicy::class)->update($staff, $order));
    }

    #[Test]
    public function customer_cannot_access_orders(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $this->bindTenant($venue->id);
        $order = $this->createOrderFixture($venue);

        $this->assertFalse(app(OrderPolicy::class)->view($customer, $order));
        $this->assertFalse(app(OrderPolicy::class)->create($customer));
    }

    #[Test]
    public function owner_cannot_manage_orders_from_another_tenant(): void
    {
        ['user' => $ownerA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $this->bindTenant($venueB->id);
        $orderB = $this->createOrderFixture($venueB);

        $this->assertFalse(app(OrderPolicy::class)->view($ownerA, $orderB));
        $this->assertFalse(app(OrderPolicy::class)->update($ownerA, $orderB));
    }

    private function createOrderFixture(?Venue $venue = null): Order
    {
        if ($venue === null) {
            ['venue' => $venue] = $this->createVenueOwner();
            $this->bindTenant($venue->id);
        }

        $event = Event::factory()->create(['venue_id' => $venue->id]);

        return Order::factory()->forEvent($event)->create();
    }
}
