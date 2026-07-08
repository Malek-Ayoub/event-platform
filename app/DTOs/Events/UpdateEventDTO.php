<?php

namespace App\DTOs\Events;

use App\DTOs\BaseDTO;

readonly class UpdateEventDTO extends BaseDTO
{
    /**
     * @param  array<int, string>|null  $gallery
     * @param  array<string, mixed>|null  $djInfo
     */
    public function __construct(
        public int $version,
        public ?string $name,
        public ?string $slug,
        public ?int $categoryId,
        public ?string $description,
        public ?string $bannerUrl,
        public ?array $gallery,
        public ?string $videoUrl,
        public ?array $djInfo,
        public ?string $startDatetime,
        public ?string $endDatetime,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (int) $data['version'],
            name: isset($data['name']) ? (string) $data['name'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            description: array_key_exists('description', $data) ? ($data['description'] !== null ? (string) $data['description'] : null) : null,
            bannerUrl: array_key_exists('banner_url', $data) ? ($data['banner_url'] !== null ? (string) $data['banner_url'] : null) : null,
            gallery: isset($data['gallery']) ? array_values($data['gallery']) : null,
            videoUrl: array_key_exists('video_url', $data) ? ($data['video_url'] !== null ? (string) $data['video_url'] : null) : null,
            djInfo: isset($data['dj_info']) ? (array) $data['dj_info'] : null,
            startDatetime: isset($data['start_datetime']) ? (string) $data['start_datetime'] : null,
            endDatetime: isset($data['end_datetime']) ? (string) $data['end_datetime'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'name' => $this->name,
            'slug' => $this->slug,
            'category_id' => $this->categoryId,
            'description' => $this->description,
            'banner_url' => $this->bannerUrl,
            'gallery' => $this->gallery,
            'video_url' => $this->videoUrl,
            'dj_info' => $this->djInfo,
            'start_datetime' => $this->startDatetime,
            'end_datetime' => $this->endDatetime,
        ], fn ($value) => $value !== null);
    }
}
