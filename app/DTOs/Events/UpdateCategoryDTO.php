<?php

namespace App\DTOs\Events;

use App\DTOs\BaseDTO;

readonly class UpdateCategoryDTO extends BaseDTO
{
    public function __construct(
        public ?string $name,
        public ?string $slug,
        public ?string $description,
        public ?int $sortOrder,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            description: array_key_exists('description', $data) ? ($data['description'] !== null ? (string) $data['description'] : null) : null,
            sortOrder: isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ], fn ($value) => $value !== null);
    }
}
