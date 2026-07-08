<?php

namespace App\DTOs\Events;

use App\DTOs\BaseDTO;

readonly class CreateTicketTypeDTO extends BaseDTO
{
    /**
     * @param  list<string>|null  $benefits
     */
    public function __construct(
        public int $eventId,
        public string $name,
        public string $price,
        public int $quantity,
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
            eventId: (int) $data['event_id'],
            name: (string) $data['name'],
            price: (string) $data['price'],
            quantity: (int) $data['quantity'],
            saleStart: isset($data['sale_start']) ? (string) $data['sale_start'] : null,
            saleEnd: isset($data['sale_end']) ? (string) $data['sale_end'] : null,
            benefits: isset($data['benefits']) ? array_values($data['benefits']) : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'sale_start' => $this->saleStart,
            'sale_end' => $this->saleEnd,
            'benefits' => $this->benefits,
            'color' => $this->color,
        ];
    }
}
