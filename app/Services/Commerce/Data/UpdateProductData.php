<?php

namespace App\Services\Commerce\Data;

use App\Models\User;

readonly class UpdateProductData
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $price,
        public ?int $eventId,
        public bool $updateEventId,
        public ?bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
