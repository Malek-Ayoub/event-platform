<?php

namespace App\DTOs\Events;

use App\DTOs\BaseDTO;

readonly class CreateEventDTO extends BaseDTO
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
        public ?string $startDatetime,
        public ?string $endDatetime,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            bannerUrl: isset($data['banner_url']) ? (string) $data['banner_url'] : null,
            gallery: isset($data['gallery']) ? array_values($data['gallery']) : null,
            videoUrl: isset($data['video_url']) ? (string) $data['video_url'] : null,
            djInfo: isset($data['dj_info']) ? (array) $data['dj_info'] : null,
            startDatetime: isset($data['start_datetime']) ? (string) $data['start_datetime'] : null,
            endDatetime: isset($data['end_datetime']) ? (string) $data['end_datetime'] : null,
        );
    }

    public function toArray(): array
    {
        return [
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
        ];
    }
}
