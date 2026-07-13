<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Contracts\VenueSubdomainLookupInterface;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeVenueSubdomainLookup;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        Cache::flush();

        $this->app->instance(
            VenueSubdomainLookupInterface::class,
            new FakeVenueSubdomainLookup(['acme' => 15]),
        );
    }

    #[Test]
    public function it_resolves_tenant_from_subdomain_middleware(): void
    {
        $response = $this
            ->withTenantHost('acme')
            ->getJson('/api/tenant/ping');

        $response
            ->assertOk()
            ->assertJson([
                'venue_id' => 15,
                'source' => 'subdomain',
            ]);
    }

    #[Test]
    public function it_returns_not_found_for_unknown_subdomain(): void
    {
        $response = $this
            ->withTenantHost('missing')
            ->getJson('/api/tenant/ping');

        $response->assertNotFound();
    }
}
