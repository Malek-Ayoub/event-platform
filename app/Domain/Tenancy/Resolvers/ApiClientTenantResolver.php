<?php

namespace App\Domain\Tenancy\Resolvers;

use App\Domain\Tenancy\Contracts\ApiClientLookupInterface;
use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\Contracts\TenantResolverInterface;
use App\Exceptions\InvalidApiClientException;
use Illuminate\Http\Request;

class ApiClientTenantResolver implements TenantResolverInterface
{
    public function __construct(
        private readonly TenantContextInterface $tenantContext,
        private readonly ApiClientLookupInterface $apiClientLookup,
    ) {}

    public function resolve(Request $request): void
    {
        $apiKey = (string) $request->header(
            (string) config('tenancy.headers.api_key'),
            '',
        );

        $secret = (string) $request->header(
            (string) config('tenancy.headers.api_secret'),
            '',
        );

        if ($apiKey === '' || $secret === '') {
            throw new InvalidApiClientException('API key and secret headers are required.');
        }

        $resolvedClient = $this->apiClientLookup->resolveByCredentials($apiKey, $secret);

        if ($resolvedClient === null) {
            throw new InvalidApiClientException('Invalid or inactive API client credentials.');
        }

        $this->tenantContext->bind(
            venueId: $resolvedClient->venueId,
            source: 'api_client',
            apiClientId: $resolvedClient->apiClientId,
            scopes: $resolvedClient->scopes,
        );

        $this->apiClientLookup->touchLastUsedAt($resolvedClient->apiClientId);
    }
}
