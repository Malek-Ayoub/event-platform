<?php

namespace Tests\Unit\Authorization;

use App\Events\Permissions\PermissionGranted;
use App\Models\Permission;
use App\Models\User;
use App\Models\Venue;
use App\Services\Authorization\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class PermissionServiceTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    private PermissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
        $this->service = app(PermissionService::class);
    }

    #[Test]
    public function super_admin_has_every_permission(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($this->service->can($admin, 'events.manage', null));
        $this->assertTrue($this->service->can($admin, 'permissions.manage', 999));
    }

    #[Test]
    public function owner_has_all_catalog_permissions_via_role(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();

        foreach (PermissionSeeder::catalog() as $permission) {
            $this->assertTrue(
                $this->service->can($owner, $permission['slug'], $venue->id),
                "Owner should have {$permission['slug']}",
            );
        }
    }

    #[Test]
    public function staff_has_default_role_permissions_only(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $this->assertTrue($this->service->can($staff, 'checkin.perform', $venue->id));
        $this->assertTrue($this->service->can($staff, 'orders.manage', $venue->id));
        $this->assertFalse($this->service->can($staff, 'permissions.manage', $venue->id));
        $this->assertFalse($this->service->can($staff, 'events.manage', $venue->id));
    }

    #[Test]
    public function customer_without_venue_membership_has_no_permissions(): void
    {
        $customer = User::factory()->create();
        $venue = Venue::factory()->create();

        $this->assertFalse($this->service->can($customer, 'orders.manage', $venue->id));
    }

    #[Test]
    public function owner_can_grant_extra_permission_to_staff(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $permission = Permission::query()->where('slug', 'events.manage')->firstOrFail();

        $this->assertFalse($this->service->can($staff, 'events.manage', $venue->id));

        $this->service->grant($owner, $staff, $permission, $venue->id);

        $this->assertTrue($this->service->can($staff, 'events.manage', $venue->id));
    }

    #[Test]
    public function staff_cannot_grant_permissions(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        ['user' => $otherStaff] = $this->createVenueStaff($venue);

        $permission = Permission::query()->where('slug', 'events.manage')->firstOrFail();

        $this->expectException(AuthorizationException::class);

        $this->service->grant($staff, $otherStaff, $permission, $venue->id);
    }

    #[Test]
    public function owner_can_revoke_granted_permission_from_staff(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $permission = Permission::query()->where('slug', 'events.manage')->firstOrFail();

        $this->service->grant($owner, $staff, $permission, $venue->id);
        $this->service->revoke($owner, $staff, $permission, $venue->id);

        $this->assertFalse($this->service->can($staff, 'events.manage', $venue->id));
    }

    #[Test]
    public function grant_dispatches_permission_granted_event(): void
    {
        Event::fake([PermissionGranted::class]);

        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $permission = Permission::query()->where('slug', 'events.manage')->firstOrFail();

        $this->service->grant($owner, $staff, $permission, $venue->id);

        Event::assertDispatched(PermissionGranted::class);
    }
}
