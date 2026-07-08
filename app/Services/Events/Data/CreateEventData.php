<?php

namespace App\Services\Events\Data;

use App\Models\User;

readonly class CreateEventData
{
    /**
     * @param  array<int, string>|null  $gallery
     * @param  array<string, mixed>|null  $djInfo
     */
    public function __construct(
        public string $name,
        public ?string $slug,
        public ?int $categoryId,
        public ?string $description,
        public ?string $bannerUrl,
        public ?array $gallery,
        public ?string $videoUrl,
        public ?array $djInfo,
        public ?\DateTimeInterface $startDatetime,
        public ?\DateTimeInterface $endDatetime,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
