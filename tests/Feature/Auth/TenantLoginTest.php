<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    #[Test]
    public function venue_member_can_login_via_tenant_subdomain(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $owner->forceFill(['password' => 'Password123!'])->save();

        $response = $this
            ->withTenantHost($venue->subdomain)
            ->postJson('/api/tenant/auth/login', [
                'email' => $owner->email,
                'password' => 'Password123!',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $owner->id)
            ->assertJsonPath('venue_id', $venue->id);
    }

    #[Test]
    public function non_member_cannot_login_via_tenant_subdomain(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create(['password' => 'Password123!']);

        $response = $this
            ->withTenantHost($venue->subdomain)
            ->postJson('/api/tenant/auth/login', [
                'email' => $outsider->email,
                'password' => 'Password123!',
            ]);

        $response->assertForbidden();
    }

    #[Test]
    public function super_admin_can_login_via_any_tenant_subdomain(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create(['password' => 'Password123!']);

        $response = $this
            ->withTenantHost($venue->subdomain)
            ->postJson('/api/tenant/auth/login', [
                'email' => $admin->email,
                'password' => 'Password123!',
            ]);

        $response->assertOk()->assertJsonPath('data.user.is_super_admin', true);
    }

    #[Test]
    public function tenant_staff_can_login(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $staff->forceFill(['password' => 'Password123!'])->save();

        $response = $this
            ->withTenantHost($venue->subdomain)
            ->postJson('/api/tenant/auth/login', [
                'email' => $staff->email,
                'password' => 'Password123!',
            ]);

        $response->assertOk()->assertJsonPath('data.user.id', $staff->id);
    }

    #[Test]
    public function tenant_user_endpoint_includes_venue_context(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();

        $response = $this
            ->withTenantHost($venue->subdomain)
            ->actingAs($owner, 'sanctum')
            ->getJson('/api/tenant/auth/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $owner->id)
            ->assertJsonPath('venue_id', $venue->id);
    }

    #[Test]
    public function unknown_subdomain_returns_not_found_for_tenant_login(): void
    {
        $response = $this
            ->withTenantHost('missing-venue')
            ->postJson('/api/tenant/auth/login', [
                'email' => 'user@example.com',
                'password' => 'Password123!',
            ]);

        $response->assertNotFound();
    }
}
