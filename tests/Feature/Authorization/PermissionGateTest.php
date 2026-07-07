<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use App\Services\Authorization\PermissionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function super_admin_bypasses_permission_gates(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('events.manage'));
        $this->assertTrue(Gate::forUser($admin)->allows('permissions.manage'));
    }

    #[Test]
    public function owner_passes_gate_with_venue_context(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();

        $this->assertTrue(Gate::forUser($owner)->allows('events.manage', $venue->id));
        $this->assertTrue(Gate::forUser($owner)->allows('permissions.manage', $venue->id));
    }

    #[Test]
    public function staff_gate_denies_unassigned_permissions(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $this->assertTrue(Gate::forUser($staff)->allows('checkin.perform', $venue->id));
        $this->assertFalse(Gate::forUser($staff)->allows('events.manage', $venue->id));
    }

    #[Test]
    public function manage_user_permissions_policy_allows_owner_not_staff(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        ['user' => $otherStaff] = $this->createVenueStaff($venue);

        $policy = app(\App\Policies\UserPermissionPolicy::class);

        $this->assertTrue($policy->manageUserPermissions($owner, $staff, $venue->id));
        $this->assertFalse($policy->manageUserPermissions($staff, $otherStaff, $venue->id));
    }

    #[Test]
    public function permission_service_matches_gate_for_staff_defaults(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $service = app(PermissionService::class);

        foreach (PermissionSeeder::catalog() as $permission) {
            $slug = $permission['slug'];
            $this->assertSame(
                $service->can($staff, $slug, $venue->id),
                Gate::forUser($staff)->allows($slug, $venue->id),
                "Mismatch for permission {$slug}",
            );
        }
    }
}
