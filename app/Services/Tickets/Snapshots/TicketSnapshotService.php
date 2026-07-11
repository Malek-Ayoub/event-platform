<?php

namespace App\Services\Tickets\Snapshots;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketSnapshot;
use InvalidArgumentException;

/**
 * Captures immutable ticket snapshots once at issuance (Phase 8.3.3b.1).
 */
final class TicketSnapshotService
{
    public function __construct(
        private TicketSnapshotMapper $mapper,
    ) {}

    public function captureForTicket(Ticket $ticket, Order $order): TicketSnapshot
    {
        $existing = TicketSnapshot::query()->where('ticket_id', $ticket->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        $event = $order->event;
        $venue = $event?->venue;

        if ($event === null || $venue === null) {
            throw new InvalidArgumentException("Order {$order->id} is missing event or venue for ticket snapshot.");
        }

        /** @var OrderItem|null $orderItem */
        $orderItem = $order->orderItems->firstWhere('ticket_type_id', $ticket->ticket_type_id);

        if ($orderItem === null) {
            throw new InvalidArgumentException(
                "Order {$order->id} is missing order item for ticket type {$ticket->ticket_type_id}.",
            );
        }

        $orderItem->loadMissing('ticketType');

        return TicketSnapshot::query()->create([
            'ticket_id' => $ticket->id,
            'payload' => $this->mapper->build($ticket, $order, $event, $venue, $orderItem),
        ]);
    }
}
