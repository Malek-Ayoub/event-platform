<?php

namespace App\DTOs\Orders;

use App\DTOs\BaseDTO;

readonly class CreatePublicOrderDTO extends BaseDTO
{
    /**
     * @param  list<CreateOrderLineItemDTO>  $lineItems
     */
    public function __construct(
        public int $eventId,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public array $lineItems,
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
            lineItems: $lineItems,
        );
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'customer_phone' => $this->customerPhone,
            'line_items' => array_map(fn (CreateOrderLineItemDTO $item) => $item->toArray(), $this->lineItems),
        ];
    }
}
