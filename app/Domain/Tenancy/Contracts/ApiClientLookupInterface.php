<?php

namespace App\Domain\Tenancy\Contracts;

use App\Domain\Tenancy\Data\ResolvedApiClientData;

interface ApiClientLookupInterface
{
    public function resolveByCredentials(string $apiKey, string $secret): ?ResolvedApiClientData;

    public function touchLastUsedAt(int $apiClientId): void;
}
