<?php

namespace App\DTOs\Events;

use App\DTOs\BaseDTO;

readonly class CreateCategoryDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public ?string $slug,
        public ?string $description,
        public int $sortOrder,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            sortOrder: isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
        ];
    }
}
