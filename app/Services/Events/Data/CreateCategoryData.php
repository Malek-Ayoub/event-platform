<?php

namespace App\Services\Events\Data;

use App\Models\User;

readonly class CreateCategoryData
{
    public function __construct(
        public string $name,
        public ?string $slug,
        public ?string $description,
        public int $sortOrder,
        public bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
