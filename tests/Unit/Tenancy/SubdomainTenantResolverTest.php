<?php

namespace Tests\Unit\Tenancy;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\Resolvers\SubdomainTenantResolver;
use App\Domain\Tenancy\Support\SubdomainExtractor;
use App\Domain\Tenancy\TenantContext;
use App\Exceptions\TenantNotResolvedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Fakes\FakeVenueSubdomainLookup;
use Tests\TestCase;

class SubdomainTenantResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        Cache::flush();
    }

    #[Test]
    public function it_resolves_tenant_from_subdomain(): void
    {
        $context = new TenantContext;
        $lookup = new FakeVenueSubdomainLookup(['acme' => 42]);

        $resolver = new SubdomainTenantResolver(
            tenantContext: $context,
            venueLookup: $lookup,
            subdomainExtractor: new SubdomainExtractor,
        );

        $request = Request::create('http://acme.localhost/api/tenant/ping', 'GET');

        $resolver->resolve($request);

        $this->assertTrue($context->isResolved());
        $this->assertSame(42, $context->getVenueId());
        $this->assertSame('subdomain', $context->getSource());
    }

    #[Test]
    public function it_throws_when_subdomain_is_unknown(): void
    {
        $resolver = new SubdomainTenantResolver(
            tenantContext: new TenantContext,
            venueLookup: new FakeVenueSubdomainLookup,
            subdomainExtractor: new SubdomainExtractor,
        );

        $request = Request::create('http://missing.localhost/api/tenant/ping', 'GET');

        $this->expectException(TenantNotResolvedException::class);

        $resolver->resolve($request);
    }

    #[Test]
    public function both_resolvers_produce_the_same_tenant_context_shape(): void
    {
        $subdomainContext = new TenantContext;
        $subdomainResolver = new SubdomainTenantResolver(
            tenantContext: $subdomainContext,
            venueLookup: new FakeVenueSubdomainLookup(['acme' => 7]),
            subdomainExtractor: new SubdomainExtractor,
        );

        $subdomainResolver->resolve(Request::create('http://acme.localhost/', 'GET'));

        $this->assertSame(7, $subdomainContext->requireVenueId());
        $this->assertSame('subdomain', $subdomainContext->getSource());
        $this->assertNull($subdomainContext->getApiClientId());
        $this->assertSame([], $subdomainContext->getScopes());

        $this->assertInstanceOf(TenantContextInterface::class, $subdomainContext);
    }
}
