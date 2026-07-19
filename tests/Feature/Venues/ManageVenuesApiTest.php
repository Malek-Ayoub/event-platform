<?php

namespace Tests\Feature\Venues;

use App\Models\ActivityLog;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageVenuesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    #[Test]
    public function super_admin_can_list_venues_with_pagination(): void
    {
        Venue::factory()->count(3)->create();
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/venues?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'subdomain', 'status', 'commission_rate', 'owner', 'created_at']],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links',
            ]);
    }

    #[Test]
    public function non_super_admin_cannot_list_venues(): void
    {
        ['user' => $owner] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/venues')
            ->assertForbidden();
    }

    #[Test]
    public function super_admin_can_show_a_venue(): void
    {
        $owner = User::factory()->create(['name' => 'Owner Name', 'email' => 'owner@show.test']);
        $venue = Venue::factory()->create([
            'name' => 'Show Hall',
            'subdomain' => 'show-hall',
            'owner_user_id' => $owner->id,
            'status' => 'active',
        ]);

        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/admin/venues/{$venue->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $venue->id)
            ->assertJsonPath('data.name', 'Show Hall')
            ->assertJsonPath('data.subdomain', 'show-hall')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.owner.name', 'Owner Name')
            ->assertJsonPath('data.owner.email', 'owner@show.test');
    }

    #[Test]
    public function non_member_cannot_show_a_venue(): void
    {
        $venue = Venue::factory()->create();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/admin/venues/{$venue->id}")
            ->assertForbidden();
    }

    #[Test]
    public function show_returns_not_found_for_missing_venue(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/venues/999999')
            ->assertNotFound();
    }

    #[Test]
    public function super_admin_can_update_name_and_commission_rate(): void
    {
        $venue = Venue::factory()->create([
            'name' => 'Old Name',
            'subdomain' => 'old-sub',
            'commission_rate' => '1.00',
        ]);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->putJson("/api/admin/venues/{$venue->id}", [
                'name' => 'New Name',
                'commission_rate' => 3.5,
                'subdomain' => 'hijacked-sub',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.commission_rate', '3.50')
            ->assertJsonPath('data.subdomain', 'old-sub');

        $venue->refresh();
        $this->assertSame('New Name', $venue->name);
        $this->assertSame('3.50', $venue->commission_rate);
        $this->assertSame('old-sub', $venue->subdomain);

        $this->assertTrue(
            ActivityLog::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('entity_type', Venue::class)
                ->where('entity_id', $venue->id)
                ->where('action', 'updated')
                ->where('actor_user_id', $admin->id)
                ->exists(),
        );
    }

    #[Test]
    public function super_admin_can_suspend_an_active_venue(): void
    {
        $venue = Venue::factory()->create(['status' => 'active']);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/venues/{$venue->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertSame('suspended', $venue->fresh()->status);

        $this->assertTrue(
            ActivityLog::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('entity_type', Venue::class)
                ->where('entity_id', $venue->id)
                ->where('action', 'suspended')
                ->where('actor_user_id', $admin->id)
                ->exists(),
        );
    }

    #[Test]
    public function suspending_an_already_suspended_venue_fails_clearly(): void
    {
        $venue = Venue::factory()->create(['status' => 'suspended']);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/venues/{$venue->id}/suspend")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Venue is already suspended.');
    }

    #[Test]
    public function super_admin_can_activate_a_suspended_venue(): void
    {
        $venue = Venue::factory()->create(['status' => 'suspended']);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/venues/{$venue->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('active', $venue->fresh()->status);

        $this->assertTrue(
            ActivityLog::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('entity_type', Venue::class)
                ->where('entity_id', $venue->id)
                ->where('action', 'activated')
                ->where('actor_user_id', $admin->id)
                ->exists(),
        );
    }

    #[Test]
    public function activating_an_already_active_venue_fails_clearly(): void
    {
        $venue = Venue::factory()->create(['status' => 'active']);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/admin/venues/{$venue->id}/activate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Venue is already active.');
    }
}
