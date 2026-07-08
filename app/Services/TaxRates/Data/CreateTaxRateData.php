<?php

namespace App\Services\TaxRates\Data;

use App\Models\User;

readonly class CreateTaxRateData
{
    public function __construct(
        public string $name,
        public string $rate,
        public bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
