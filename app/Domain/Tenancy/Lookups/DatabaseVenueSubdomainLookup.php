<?php

namespace App\Domain\Tenancy\Lookups;

use App\Domain\Tenancy\Contracts\VenueSubdomainLookupInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseVenueSubdomainLookup implements VenueSubdomainLookupInterface
{
    public function findActiveVenueIdBySubdomain(string $subdomain): ?int
    {
        if (! Schema::hasTable('venues')) {
            return null;
        }

        $query = DB::table('venues')
            ->where('subdomain', $subdomain);

        if (Schema::hasColumn('venues', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $venueId = $query->value('id');

        return $venueId !== null ? (int) $venueId : null;
    }
}
