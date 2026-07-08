<?php

namespace App\DTOs\Orders;

use App\DTOs\BaseDTO;

readonly class CreateOrderLineItemDTO extends BaseDTO
{
    public function __construct(
        public int $ticketTypeId,
        public int $quantity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ticketTypeId: (int) $data['ticket_type_id'],
            quantity: (int) $data['quantity'],
        );
    }

    public function toArray(): array
    {
        return [
            'ticket_type_id' => $this->ticketTypeId,
            'quantity' => $this->quantity,
        ];
    }
}
