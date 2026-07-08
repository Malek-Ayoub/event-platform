<?php

namespace App\DTOs\Commerce;

use App\DTOs\BaseDTO;

readonly class UpdateProductDTO extends BaseDTO
{
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?string $price,
        public ?int $eventId,
        public bool $updateEventId,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? ($data['description'] !== null ? (string) $data['description'] : null) : null,
            price: isset($data['price']) ? (string) $data['price'] : null,
            eventId: array_key_exists('event_id', $data) && $data['event_id'] !== null ? (int) $data['event_id'] : null,
            updateEventId: array_key_exists('event_id', $data),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'event_id' => $this->updateEventId ? $this->eventId : null,
            'is_active' => $this->isActive,
        ], fn ($value) => $value !== null);
    }
}
