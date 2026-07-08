<?php

namespace App\DTOs\Orders;

use App\DTOs\BaseDTO;

readonly class CreateOrderDTO extends BaseDTO
{
    /**
     * @param  list<CreateOrderLineItemDTO>  $lineItems
     */
    public function __construct(
        public int $eventId,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public ?int $customerUserId,
        public array $lineItems,
        public ?int $reservationId,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $lineItems = array_map(
            fn (array $item): CreateOrderLineItemDTO => CreateOrderLineItemDTO::fromArray($item),
            $data['line_items'],
        );

        return new self(
            eventId: (int) $data['event_id'],
            customerName: (string) $data['customer_name'],
            customerEmail: (string) $data['customer_email'],
            customerPhone: isset($data['customer_phone']) ? (string) $data['customer_phone'] : null,
            customerUserId: isset($data['customer_user_id']) ? (int) $data['customer_user_id'] : null,
            lineItems: $lineItems,
            reservationId: isset($data['reservation_id']) ? (int) $data['reservation_id'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'customer_user_id' => $this->customerUserId,
            'line_items' => array_map(fn (CreateOrderLineItemDTO $item) => $item->toArray(), $this->lineItems),
            'reservation_id' => $this->reservationId,
        ];
    }
}
