<?php

namespace App\DTOs\Events;

use App\DTOs\BaseDTO;

readonly class UpdateTicketTypeDTO extends BaseDTO
{
    /**
     * @param  list<string>|null  $benefits
     */
    public function __construct(
        public int $version,
        public ?string $name,
        public ?string $price,
        public ?int $quantity,
        public ?string $saleStart,
        public ?string $saleEnd,
        public ?array $benefits,
        public ?string $color,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (int) $data['version'],
            name: isset($data['name']) ? (string) $data['name'] : null,
            price: isset($data['price']) ? (string) $data['price'] : null,
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            saleStart: isset($data['sale_start']) ? (string) $data['sale_start'] : null,
            saleEnd: isset($data['sale_end']) ? (string) $data['sale_end'] : null,
            benefits: isset($data['benefits']) ? array_values($data['benefits']) : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'sale_start' => $this->saleStart,
            'sale_end' => $this->saleEnd,
            'benefits' => $this->benefits,
            'color' => $this->color,
        ], fn ($value) => $value !== null);
    }
}
