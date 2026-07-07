<?php

namespace Tests\Support\Fakes;

use App\Domain\Tenancy\Contracts\VenueSubdomainLookupInterface;

class FakeVenueSubdomainLookup implements VenueSubdomainLookupInterface
{
    /** @var array<string, int> */
    private array $map;

    /**
     * @param  array<string, int>  $map
     */
    public function __construct(array $map = [])
    {
        $this->map = $map;
    }

    public function findActiveVenueIdBySubdomain(string $subdomain): ?int
    {
        return $this->map[$subdomain] ?? null;
    }
}
