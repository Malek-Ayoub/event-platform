<?php

namespace App\Domain\Tenancy\Lookups;

use App\Domain\Tenancy\Contracts\ApiClientLookupInterface;
use App\Domain\Tenancy\Data\ResolvedApiClientData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseApiClientLookup implements ApiClientLookupInterface
{
    public function resolveByCredentials(string $apiKey, string $secret): ?ResolvedApiClientData
    {
        if (! Schema::hasTable('api_clients')) {
            return null;
        }

        $client = DB::table('api_clients')
            ->where('api_key', $apiKey)
            ->where('active', true)
            ->first();

        if ($client === null) {
            return null;
        }

        if (isset($client->expires_at) && $client->expires_at !== null && now()->greaterThan($client->expires_at)) {
            return null;
        }

        if (! Hash::check($secret, (string) $client->secret)) {
            return null;
        }

        $scopes = [];

        if (isset($client->scopes) && is_string($client->scopes) && $client->scopes !== '') {
            $decoded = json_decode($client->scopes, true);
            $scopes = is_array($decoded) ? array_values($decoded) : [];
        }

        return new ResolvedApiClientData(
            apiClientId: (int) $client->id,
            venueId: (int) $client->venue_id,
            scopes: $scopes,
        );
    }

    public function touchLastUsedAt(int $apiClientId): void
    {
        if (! Schema::hasTable('api_clients') || ! Schema::hasColumn('api_clients', 'last_used_at')) {
            return;
        }

        DB::table('api_clients')
            ->where('id', $apiClientId)
            ->update(['last_used_at' => now()]);
    }
}
