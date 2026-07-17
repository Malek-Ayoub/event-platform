<?php

namespace App\Support\Http\Orders;

use App\DTOs\Orders\CreateOrderDTO;
use App\DTOs\Orders\CreatePublicOrderDTO;
use App\Models\User;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;

class OrderRequestMapper
{
    public static function toCreateOrderData(CreateOrderDTO $dto, ?User $actor, ?string $ipAddress): CreateOrderData
    {
        return new CreateOrderData(
            eventId: $dto->eventId,
            customerName: $dto->customerName,
            customerEmail: $dto->customerEmail,
            customerPhone: $dto->customerPhone,
            customerUserId: $dto->customerUserId,
            lineItems: array_map(
                fn ($item) => new CreateOrderLineItemData($item->ticketTypeId, $item->quantity),
                $dto->lineItems,
            ),
            reservationId: $dto->reservationId,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }

    /**
     * Guest checkout mapping: actor, customer_user_id, and reservation_id are always null.
     */
    public static function toGuestCreateOrderData(CreatePublicOrderDTO $dto, ?string $ipAddress): CreateOrderData
    {
        return new CreateOrderData(
            eventId: $dto->eventId,
            customerName: $dto->customerName,
            customerEmail: $dto->customerEmail,
            customerPhone: $dto->customerPhone,
            customerUserId: null,
            lineItems: array_map(
                fn ($item) => new CreateOrderLineItemData($item->ticketTypeId, $item->quantity),
                $dto->lineItems,
            ),
            reservationId: null,
            actor: null,
            ipAddress: $ipAddress,
        );
    }
}
