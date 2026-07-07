<?php

namespace App\Services\Orders\Data;

use App\Models\TicketType;

readonly class ResolvedOrderLineItemData
{
    public function __construct(
        public TicketType $ticketType,
        public int $quantity,
        public string $unitPrice,
    ) {}
}
