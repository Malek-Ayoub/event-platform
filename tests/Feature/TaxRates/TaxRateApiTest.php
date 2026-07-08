<?php

namespace Tests\Feature\TaxRates;

use App\Models\TaxRate;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxRateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    /**
     * @return array{owner: User, venue: Venue, token: string}
     */
    private function authenticateVenueOwner(): array
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);
        $this->bindTenant($venue->id);

        return ['owner' => $owner, 'venue' => $venue, 'token' => $token];
    }

    #[Test]
    public function owner_can_create_and_list_tax_rates(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $create = $this->withToken($token)->postJson('/api/tenant/tax-rates', [
            'name' => 'VAT',
            'rate' => '0.1500',
            'is_active' => true,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.name', 'VAT')
            ->assertJsonPath('data.rate', '0.1500')
            ->assertJsonPath('data.version', 1);

        $this->withToken($token)->getJson('/api/tenant/tax-rates')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->assertDatabaseHas('tax_rates', [
            'venue_id' => $venue->id,
            'name' => 'VAT',
        ]);
    }

    #[Test]
    public function owner_can_update_tax_rate_with_version(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $taxRate = TaxRate::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Sales Tax',
            'rate' => 0.05,
            'version' => 1,
        ]);

        $this->withToken($token)->putJson("/api/tenant/tax-rates/{$taxRate->id}", [
            'version' => 1,
            'rate' => '0.0750',
        ])
            ->assertOk()
            ->assertJsonPath('data.rate', '0.0750')
            ->assertJsonPath('data.version', 2);
    }

    #[Test]
    public function staff_without_settings_permission_cannot_create_tax_rates(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $token = $staff->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->postJson('/api/tenant/tax-rates', [
                'name' => 'Blocked',
                'rate' => '0.10',
            ])
            ->assertForbidden();
    }
}
