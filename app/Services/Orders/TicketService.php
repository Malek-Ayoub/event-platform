<?php

namespace App\Services\Orders;

use App\Enums\OrdersDomain\TicketStatus;
use App\Exceptions\Orders\InsufficientTicketsException;
use App\Exceptions\Orders\InvalidTicketTypeException;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Orders\Data\CreateOrderLineItemData;

class TicketService
{
    public function __construct(
        private TicketSerialService $ticketSerialService,
    ) {}

    /**
     * @param  list<CreateOrderLineItemData>  $lineItems
     * @return list<Ticket>
     */
    public function createForOrder(Order $order, Event $event, array $lineItems): array
    {
        $tickets = [];

        foreach ($lineItems as $lineItem) {
            $tickets = array_merge(
                $tickets,
                $this->createTicketsForLineItem($order, $event, $lineItem),
            );
        }

        return $tickets;
    }

    /**
     * @return list<Ticket>
     */
    private function createTicketsForLineItem(
        Order $order,
        Event $event,
        CreateOrderLineItemData $lineItem,
    ): array {
        if ($lineItem->quantity < 1) {
            return [];
        }

        $ticketType = TicketType::query()
            ->whereKey($lineItem->ticketTypeId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($ticketType->event_id !== $order->event_id) {
            throw InvalidTicketTypeException::forOrder($ticketType->id, (int) $order->id);
        }

        $available = $ticketType->quantity - $ticketType->quantity_sold;

        if ($lineItem->quantity > $available) {
            throw InsufficientTicketsException::forTicketType(
                $ticketType->id,
                $lineItem->quantity,
                max(0, $available),
            );
        }

        $created = [];

        for ($i = 0; $i < $lineItem->quantity; $i++) {
            $serial = $this->ticketSerialService->nextSerial($event);

            $created[] = Ticket::query()->create([
                'venue_id' => $order->venue_id,
                'event_id' => $order->event_id,
                'order_id' => $order->id,
                'ticket_type_id' => $ticketType->id,
                'serial' => $serial,
                'qr_code_path' => $this->qrCodePath($event->id, $serial),
                'status' => TicketStatus::Valid,
            ]);
        }

        $ticketType->update([
            'quantity_sold' => $ticketType->quantity_sold + $lineItem->quantity,
        ]);

        return $created;
    }

    private function qrCodePath(int $eventId, string $serial): string
    {
        return "tickets/{$eventId}/{$serial}.png";
    }
}
