<?php

namespace App\Services\Commerce\Data;

use App\Models\User;

readonly class CreateProductData
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $price,
        public ?int $eventId,
        public bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
