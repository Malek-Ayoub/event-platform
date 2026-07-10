<?php

namespace Tests\Feature;

use App\Domain\Tenancy\Contracts\ApiClientLookupInterface;
use App\Domain\Tenancy\Contracts\VenueSubdomainLookupInterface;
use App\Domain\Tenancy\Data\ResolvedApiClientData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeApiClientLookup;
use Tests\Support\Fakes\FakeVenueSubdomainLookup;
use Tests\TestCase;

class ApplicationSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        Cache::flush();
    }

    #[Test]
    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }

    #[Test]
    public function test_api_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => config('app.name'),
            ]);
    }

    #[Test]
    public function test_tenant_route_placeholder_is_registered(): void
    {
        $this->app->instance(
            VenueSubdomainLookupInterface::class,
            new FakeVenueSubdomainLookup(['acme' => 1]),
        );

        $response = $this
            ->withTenantHost('acme')
            ->getJson('/api/tenant/ping');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'tenant route placeholder',
                'venue_id' => 1,
                'source' => 'subdomain',
            ]);
    }

    #[Test]
    public function test_api_client_route_placeholder_is_registered(): void
    {
        $lookup = new FakeApiClientLookup;
        $lookup->register('smoke-key', 'smoke-secret', new ResolvedApiClientData(
            apiClientId: 1,
            venueId: 2,
            scopes: [],
        ));

        $this->app->instance(ApiClientLookupInterface::class, $lookup);

        $response = $this
            ->withHeaders([
                'X-Api-Key' => 'smoke-key',
                'X-Api-Secret' => 'smoke-secret',
            ])
            ->getJson('/api/partner/ping');

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'api client route placeholder',
                'venue_id' => 2,
                'source' => 'api_client',
            ]);
    }
}
