<?php

namespace Tests\Unit\Authorization;

use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use App\Models\UserPermission;
use App\Models\Venue;
use App\Policies\UserPermissionPolicy;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class UserPermissionPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    private UserPermissionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
        $this->policy = new UserPermissionPolicy(new TenantContext);
    }

    #[Test]
    public function owner_can_manage_staff_permissions_at_same_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $this->assertTrue($this->policy->manageUserPermissions($owner, $staff, $venue->id));
    }

    #[Test]
    public function staff_cannot_manage_permissions(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        ['user' => $otherStaff] = $this->createVenueStaff($venue);

        $this->assertFalse($this->policy->manageUserPermissions($staff, $otherStaff, $venue->id));
    }

    #[Test]
    public function owner_cannot_manage_permissions_for_users_outside_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create();

        $this->assertFalse($this->policy->manageUserPermissions($owner, $outsider, $venue->id));
    }

    #[Test]
    public function super_admin_can_manage_permissions_for_any_staff(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($this->policy->manageUserPermissions($admin, $staff, $venue->id));
    }

    #[Test]
    public function staff_cannot_grant_permissions_to_themselves(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $this->assertFalse($this->policy->manageUserPermissions($staff, $staff, $venue->id));
    }
}
