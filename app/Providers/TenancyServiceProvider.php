<?php

namespace App\Providers;

use App\Domain\Tenancy\Contracts\ApiClientLookupInterface;
use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\Contracts\TenantResolverInterface;
use App\Domain\Tenancy\Contracts\VenueSubdomainLookupInterface;
use App\Domain\Tenancy\Lookups\DatabaseApiClientLookup;
use App\Domain\Tenancy\Lookups\DatabaseVenueSubdomainLookup;
use App\Domain\Tenancy\Resolvers\ApiClientTenantResolver;
use App\Domain\Tenancy\Resolvers\SubdomainTenantResolver;
use App\Domain\Tenancy\Support\SubdomainExtractor;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContextInterface::class, TenantContext::class);

        $this->app->singleton(SubdomainExtractor::class);

        $this->app->bind(VenueSubdomainLookupInterface::class, DatabaseVenueSubdomainLookup::class);
        $this->app->bind(ApiClientLookupInterface::class, DatabaseApiClientLookup::class);

        $this->app->bind(SubdomainTenantResolver::class, function ($app): SubdomainTenantResolver {
            return new SubdomainTenantResolver(
                tenantContext: $app->make(TenantContextInterface::class),
                venueLookup: $app->make(VenueSubdomainLookupInterface::class),
                subdomainExtractor: $app->make(SubdomainExtractor::class),
            );
        });

        $this->app->bind(ApiClientTenantResolver::class, function ($app): ApiClientTenantResolver {
            return new ApiClientTenantResolver(
                tenantContext: $app->make(TenantContextInterface::class),
                apiClientLookup: $app->make(ApiClientLookupInterface::class),
            );
        });

        $this->app->bind('tenancy.resolver.subdomain', SubdomainTenantResolver::class);
        $this->app->bind('tenancy.resolver.api_client', ApiClientTenantResolver::class);

        $this->app->bind(TenantResolverInterface::class.'.subdomain', SubdomainTenantResolver::class);
        $this->app->bind(TenantResolverInterface::class.'.api_client', ApiClientTenantResolver::class);
    }

    public function boot(): void
    {
        //
    }
}
