<?php

namespace App\Domain\Tenancy\Data;

readonly class ResolvedApiClientData
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public int $apiClientId,
        public int $venueId,
        public array $scopes = [],
    ) {}
}
