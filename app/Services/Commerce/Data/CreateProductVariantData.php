<?php

namespace App\Services\Commerce\Data;

use App\Models\User;

readonly class CreateProductVariantData
{
    public function __construct(
        public int $productId,
        public string $name,
        public ?string $sku,
        public ?string $priceOverride,
        public bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
