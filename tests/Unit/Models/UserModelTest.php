<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_detects_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertFalse($user->isSuperAdmin());
    }

    #[Test]
    public function it_checks_venue_membership(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create();

        $this->assertTrue($owner->belongsToVenue($venue->id));
        $this->assertFalse($outsider->belongsToVenue($venue->id));
    }

    #[Test]
    public function super_admin_belongs_to_any_venue(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $venue = Venue::factory()->create();

        $this->assertTrue($admin->belongsToVenue($venue->id));
    }

    #[Test]
    public function it_returns_venue_membership_pivot(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();

        $membership = $owner->venueMembership($venue->id);

        $this->assertInstanceOf(VenueUser::class, $membership);
        $this->assertTrue($membership->isOwner());
    }
}
