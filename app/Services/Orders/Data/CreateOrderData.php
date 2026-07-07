<?php

namespace App\Services\Orders\Data;

use App\Models\User;

readonly class CreateOrderData
{
    /**
     * @param  list<CreateOrderLineItemData>  $lineItems
     */
    public function __construct(
        public int $eventId,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerPhone,
        public ?int $customerUserId,
        public array $lineItems,
        public ?int $reservationId = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
