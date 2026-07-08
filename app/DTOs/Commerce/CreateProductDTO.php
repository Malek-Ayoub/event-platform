<?php

namespace App\DTOs\Commerce;

use App\DTOs\BaseDTO;

readonly class CreateProductDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $price,
        public ?int $eventId,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            price: (string) $data['price'],
            eventId: isset($data['event_id']) ? (int) $data['event_id'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'event_id' => $this->eventId,
            'is_active' => $this->isActive,
        ];
    }
}
