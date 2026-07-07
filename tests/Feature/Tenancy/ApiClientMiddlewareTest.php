<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Contracts\ApiClientLookupInterface;
use App\Domain\Tenancy\Data\ResolvedApiClientData;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeApiClientLookup;
use Tests\TestCase;

class ApiClientMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $lookup = new FakeApiClientLookup;
        $lookup->register('partner-key', 'partner-secret', new ResolvedApiClientData(
            apiClientId: 8,
            venueId: 22,
            scopes: ['events.read'],
        ));

        $this->app->instance(ApiClientLookupInterface::class, $lookup);
    }

    #[Test]
    public function it_resolves_tenant_from_api_client_middleware(): void
    {
        $response = $this
            ->withHeaders([
                'X-Api-Key' => 'partner-key',
                'X-Api-Secret' => 'partner-secret',
            ])
            ->getJson('/api/partner/ping');

        $response
            ->assertOk()
            ->assertJson([
                'venue_id' => 22,
                'source' => 'api_client',
                'api_client_id' => 8,
                'scopes' => ['events.read'],
            ]);
    }

    #[Test]
    public function it_returns_unauthorized_for_invalid_api_client(): void
    {
        $response = $this
            ->withHeaders([
                'X-Api-Key' => 'bad-key',
                'X-Api-Secret' => 'bad-secret',
            ])
            ->getJson('/api/partner/ping');

        $response->assertUnauthorized();
    }
}
