<?php

namespace App\Services\Events\Data;

use App\Models\User;

readonly class UpdateEventData
{
    /**
     * @param  array<int, string>|null  $gallery
     * @param  array<string, mixed>|null  $djInfo
     */
    public function __construct(
        public int $expectedVersion,
        public ?string $name = null,
        public ?string $slug = null,
        public ?int $categoryId = null,
        public ?string $description = null,
        public ?string $bannerUrl = null,
        public ?array $gallery = null,
        public ?string $videoUrl = null,
        public ?array $djInfo = null,
        public ?\DateTimeInterface $startDatetime = null,
        public ?\DateTimeInterface $endDatetime = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
