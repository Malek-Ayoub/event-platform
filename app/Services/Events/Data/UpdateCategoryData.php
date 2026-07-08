<?php

namespace App\Services\Events\Data;

use App\Models\User;

readonly class UpdateCategoryData
{
    public function __construct(
        public ?string $name = null,
        public ?string $slug = null,
        public ?string $description = null,
        public ?int $sortOrder = null,
        public ?bool $isActive = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
