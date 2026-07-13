<?php

namespace Tests\Unit\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use App\Policies\VenuePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VenuePolicyTest extends TestCase
{
    use RefreshDatabase;

    private VenuePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new VenuePolicy(new TenantContext);
    }

    #[Test]
    public function super_admin_can_create_venues(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($this->policy->create($admin));
    }

    #[Test]
    public function owner_can_update_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();

        $this->assertTrue($this->policy->update($owner, $venue));
    }

    #[Test]
    public function staff_cannot_update_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);

        $this->assertFalse($this->policy->update($staff, $venue));
    }

    #[Test]
    public function venue_member_can_view_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();

        $this->assertTrue($this->policy->view($owner, $venue));
    }

    #[Test]
    public function outsider_cannot_view_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create();

        $this->assertFalse($this->policy->view($outsider, $venue));
    }

    #[Test]
    public function only_super_admin_can_delete_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $outsider = User::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $venue));
        $this->assertFalse($this->policy->delete($outsider, $venue));
    }
}
