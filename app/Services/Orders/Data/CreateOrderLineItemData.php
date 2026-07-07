<?php

namespace App\Services\Orders\Data;

readonly class CreateOrderLineItemData
{
    public function __construct(
        public int $ticketTypeId,
        public int $quantity,
    ) {}
}
