<?php

namespace Tests\Support\Fakes;

use App\Domain\Tenancy\Contracts\ApiClientLookupInterface;
use App\Domain\Tenancy\Data\ResolvedApiClientData;

class FakeApiClientLookup implements ApiClientLookupInterface
{
    /** @var array<string, ResolvedApiClientData> */
    private array $clients = [];

    private int $lastTouchedId = 0;

    public function register(string $apiKey, string $secret, ResolvedApiClientData $data): void
    {
        $this->clients[$this->credentialKey($apiKey, $secret)] = $data;
    }

    public function resolveByCredentials(string $apiKey, string $secret): ?ResolvedApiClientData
    {
        return $this->clients[$this->credentialKey($apiKey, $secret)] ?? null;
    }

    public function touchLastUsedAt(int $apiClientId): void
    {
        $this->lastTouchedId = $apiClientId;
    }

    public function lastTouchedId(): int
    {
        return $this->lastTouchedId;
    }

    private function credentialKey(string $apiKey, string $secret): string
    {
        return $apiKey.'|'.$secret;
    }
}
