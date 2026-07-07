<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Exceptions\TenantNotResolvedException;

class TenantContext implements TenantContextInterface
{
    private ?int $venueId = null;

    private ?string $source = null;

    private ?int $apiClientId = null;

    /** @var list<string> */
    private array $scopes = [];

    public function bind(
        int $venueId,
        string $source,
        ?int $apiClientId = null,
        array $scopes = [],
    ): void {
        $this->venueId = $venueId;
        $this->source = $source;
        $this->apiClientId = $apiClientId;
        $this->scopes = array_values($scopes);
    }

    public function isResolved(): bool
    {
        return $this->venueId !== null;
    }

    public function getVenueId(): ?int
    {
        return $this->venueId;
    }

    public function requireVenueId(): int
    {
        if ($this->venueId === null) {
            throw new TenantNotResolvedException;
        }

        return $this->venueId;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getApiClientId(): ?int
    {
        return $this->apiClientId;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function clear(): void
    {
        $this->venueId = null;
        $this->source = null;
        $this->apiClientId = null;
        $this->scopes = [];
    }
}
