<?php

namespace App\Domain\Tenancy\Contracts;

interface TenantContextInterface
{
    public function bind(
        int $venueId,
        string $source,
        ?int $apiClientId = null,
        array $scopes = [],
    ): void;

    public function isResolved(): bool;

    public function getVenueId(): ?int;

    public function requireVenueId(): int;

    public function getSource(): ?string;

    public function getApiClientId(): ?int;

    /**
     * @return list<string>
     */
    public function getScopes(): array;

    public function hasScope(string $scope): bool;

    public function clear(): void;
}
