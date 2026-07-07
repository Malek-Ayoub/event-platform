<?php

namespace App\Domain\Tenancy\Resolvers;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\Contracts\TenantResolverInterface;
use App\Domain\Tenancy\Contracts\VenueSubdomainLookupInterface;
use App\Domain\Tenancy\Support\SubdomainExtractor;
use App\Exceptions\TenantNotResolvedException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SubdomainTenantResolver implements TenantResolverInterface
{
    public function __construct(
        private readonly TenantContextInterface $tenantContext,
        private readonly VenueSubdomainLookupInterface $venueLookup,
        private readonly SubdomainExtractor $subdomainExtractor,
    ) {}

    public function resolve(Request $request): void
    {
        $subdomain = $this->subdomainExtractor->extract(
            $request->getHost(),
            (string) config('tenancy.base_domain'),
        );

        if ($subdomain === null) {
            throw new TenantNotResolvedException('Unable to resolve tenant subdomain from request host.');
        }

        $cacheKey = sprintf(
            '%s:subdomain:%s',
            config('tenancy.cache_prefix'),
            $subdomain,
        );

        $venueId = Cache::remember(
            $cacheKey,
            config('tenancy.subdomain_cache_ttl'),
            fn (): ?int => $this->venueLookup->findActiveVenueIdBySubdomain($subdomain),
        );

        if ($venueId === null) {
            throw new TenantNotResolvedException(sprintf('No active venue found for subdomain [%s].', $subdomain));
        }

        $this->tenantContext->bind(
            venueId: $venueId,
            source: 'subdomain',
        );
    }
}
