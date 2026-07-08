<?php

namespace App\Services\TaxRates\Data;

use App\Models\User;

readonly class UpdateTaxRateData
{
    public function __construct(
        public int $expectedVersion,
        public ?string $name,
        public ?string $rate,
        public ?bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
