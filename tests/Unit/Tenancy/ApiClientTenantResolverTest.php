<?php

namespace Tests\Unit\Tenancy;

use App\Domain\Tenancy\Data\ResolvedApiClientData;
use App\Domain\Tenancy\Resolvers\ApiClientTenantResolver;
use App\Domain\Tenancy\TenantContext;
use App\Exceptions\InvalidApiClientException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeApiClientLookup;
use Tests\TestCase;

class ApiClientTenantResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_tenant_from_api_client_credentials(): void
    {
        $lookup = new FakeApiClientLookup;
        $lookup->register('key-1', 'secret-1', new ResolvedApiClientData(
            apiClientId: 5,
            venueId: 99,
            scopes: ['orders.read'],
        ));

        $context = new TenantContext;
        $resolver = new ApiClientTenantResolver($context, $lookup);

        $request = Request::create('/api/partner/ping', 'GET', server: [
            'HTTP_X_API_KEY' => 'key-1',
            'HTTP_X_API_SECRET' => 'secret-1',
        ]);

        $resolver->resolve($request);

        $this->assertSame(99, $context->getVenueId());
        $this->assertSame('api_client', $context->getSource());
        $this->assertSame(5, $context->getApiClientId());
        $this->assertTrue($context->hasScope('orders.read'));
        $this->assertSame(5, $lookup->lastTouchedId());
    }

    #[Test]
    public function it_rejects_invalid_api_client_credentials(): void
    {
        $resolver = new ApiClientTenantResolver(
            tenantContext: new TenantContext,
            apiClientLookup: new FakeApiClientLookup,
        );

        $request = Request::create('/api/partner/ping', 'GET', server: [
            'HTTP_X_API_KEY' => 'invalid',
            'HTTP_X_API_SECRET' => 'invalid',
        ]);

        $this->expectException(InvalidApiClientException::class);

        $resolver->resolve($request);
    }

    #[Test]
    public function it_requires_api_key_and_secret_headers(): void
    {
        $resolver = new ApiClientTenantResolver(
            tenantContext: new TenantContext,
            apiClientLookup: new FakeApiClientLookup,
        );

        $this->expectException(InvalidApiClientException::class);

        $resolver->resolve(Request::create('/api/partner/ping', 'GET'));
    }
}
