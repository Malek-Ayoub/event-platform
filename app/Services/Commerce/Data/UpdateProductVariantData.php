<?php

namespace App\Services\Commerce\Data;

use App\Models\User;

readonly class UpdateProductVariantData
{
    public function __construct(
        public ?string $name,
        public ?string $sku,
        public ?string $priceOverride,
        public ?bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
