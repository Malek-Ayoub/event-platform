<?php

namespace Tests\Feature\Venues;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateVenueApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Harbor Hall',
            'subdomain' => 'harbor-hall',
            'owner_name' => 'Sam Organizer',
            'owner_email' => 'owner@harbor.test',
            'owner_password' => 'Password123!',
        ], $overrides);
    }

    #[Test]
    public function super_admin_can_create_a_venue_with_owner(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/admin/venues', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Harbor Hall')
            ->assertJsonPath('data.slug', 'harbor-hall')
            ->assertJsonPath('data.subdomain', 'harbor-hall')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.commission_rate', '1.00')
            ->assertJsonPath('data.owner.name', 'Sam Organizer')
            ->assertJsonPath('data.owner.email', 'owner@harbor.test');

        $venueId = (int) $response->json('data.id');
        $venue = Venue::query()->findOrFail($venueId);
        $owner = User::query()->where('email', 'owner@harbor.test')->firstOrFail();

        $this->assertSame($owner->id, $venue->owner_user_id);
        $this->assertNotNull($owner->email_verified_at);
        $this->assertTrue(Hash::check('Password123!', $owner->password));

        $this->assertDatabaseHas('venue_user', [
            'venue_id' => $venue->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $this->assertSame(1, VenueUser::query()->where('venue_id', $venue->id)->count());
    }

    #[Test]
    public function venue_owner_cannot_create_venues(): void
    {
        ['user' => $owner] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/admin/venues', $this->validPayload([
                'subdomain' => 'another-hall',
                'owner_email' => 'new-owner@harbor.test',
            ]))
            ->assertForbidden();
    }

    #[Test]
    public function it_rejects_duplicate_subdomain(): void
    {
        Venue::factory()->create(['subdomain' => 'taken-hall']);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/admin/venues', $this->validPayload([
                'subdomain' => 'taken-hall',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subdomain']);
    }

    #[Test]
    public function it_rejects_duplicate_owner_email(): void
    {
        User::factory()->create(['email' => 'taken@harbor.test']);
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/admin/venues', $this->validPayload([
                'owner_email' => 'taken@harbor.test',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_email']);
    }

    #[Test]
    public function it_rejects_reserved_subdomains(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        foreach (['www', 'api', 'admin'] as $reserved) {
            $this->withToken($token)
                ->postJson('/api/admin/venues', $this->validPayload([
                    'subdomain' => $reserved,
                    'owner_email' => "{$reserved}@harbor.test",
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['subdomain']);
        }
    }

    #[Test]
    public function it_derives_slug_from_name(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/admin/venues', $this->validPayload([
                'name' => 'Blue Sky Arena',
                'subdomain' => 'blue-sky',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'blue-sky-arena');
    }

    #[Test]
    public function created_owner_can_login_via_tenant_auth(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/admin/venues', $this->validPayload([
                'subdomain' => 'login-hall',
                'owner_email' => 'login-owner@harbor.test',
                'owner_password' => 'Password123!',
            ]))
            ->assertCreated();

        $this->withTenantHost('login-hall')
            ->postJson('/api/tenant/auth/login', [
                'email' => 'login-owner@harbor.test',
                'password' => 'Password123!',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'login-owner@harbor.test');
    }
}
