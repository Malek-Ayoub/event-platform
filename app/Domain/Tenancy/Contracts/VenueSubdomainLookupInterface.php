<?php

namespace App\Domain\Tenancy\Contracts;

interface VenueSubdomainLookupInterface
{
    public function findActiveVenueIdBySubdomain(string $subdomain): ?int;
}
