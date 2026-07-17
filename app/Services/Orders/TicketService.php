<?php

namespace App\Services\Orders;

use App\Enums\OrdersDomain\TicketStatus;
use App\Exceptions\Orders\InsufficientTicketsException;
use App\Exceptions\Orders\InvalidTicketTypeException;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Orders\Data\ResolvedOrderLineItemData;

class TicketService
{
    public function __construct(
        private TicketSerialService $ticketSerialService,
        private TicketNumberGenerator $ticketNumberGenerator,
        private QrTokenGenerator $qrTokenGenerator,
    ) {}

    /**
     * Reserves inventory when an order is created (before payment).
     *
     * @param  list<ResolvedOrderLineItemData>  $lineItems
     */
    public function reserveInventoryForOrder(Order $order, Event $event, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $this->reserveInventoryForLineItem($order, $event, $lineItem);
        }
    }

    /**
     * Releases previously reserved inventory when a pending order is cancelled/expired.
     *
     * Each ticket type is locked with `lockForUpdate()` because this runs in a new
     * transaction — the locks from the original order creation are long gone.
     *
     * @param  list<ResolvedOrderLineItemData>  $lineItems
     */
    public function releaseInventoryForOrder(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $this->releaseInventoryForLineItem($order, $lineItem);
        }
    }

    /**
     * Creates ticket rows for a paid order. Inventory must already be reserved.
     *
     * @param  list<ResolvedOrderLineItemData>  $lineItems
     * @return list<Ticket>
     */
    public function issueForOrder(Order $order, Event $event, array $lineItems): array
    {
        $tickets = [];

        foreach ($lineItems as $lineItem) {
            $tickets = array_merge(
                $tickets,
                $this->issueTicketsForLineItem($order, $event, $lineItem),
            );
        }

        return $tickets;
    }

    private function reserveInventoryForLineItem(
        Order $order,
        Event $event,
        ResolvedOrderLineItemData $lineItem,
    ): void {
        if ($lineItem->quantity < 1) {
            return;
        }

        $ticketType = $lineItem->ticketType;

        if ($ticketType->event_id !== $event->id) {
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

        $ticketType->update([
            'quantity_sold' => $ticketType->quantity_sold + $lineItem->quantity,
        ]);
    }

    private function releaseInventoryForLineItem(
        Order $order,
        ResolvedOrderLineItemData $lineItem,
    ): void {
        if ($lineItem->quantity < 1) {
            return;
        }

        $ticketType = TicketType::query()
            ->whereKey($lineItem->ticketType->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($ticketType->event_id !== $order->event_id) {
            throw InvalidTicketTypeException::forOrder($ticketType->id, (int) $order->id);
        }

        $ticketType->update([
            'quantity_sold' => max(0, $ticketType->quantity_sold - $lineItem->quantity),
        ]);
    }

    /**
     * @return list<Ticket>
     */
    private function issueTicketsForLineItem(
        Order $order,
        Event $event,
        ResolvedOrderLineItemData $lineItem,
    ): array {
        if ($lineItem->quantity < 1) {
            return [];
        }

        $ticketType = $lineItem->ticketType;

        if ($ticketType->event_id !== $order->event_id) {
            throw InvalidTicketTypeException::forOrder($ticketType->id, (int) $order->id);
        }

        $created = [];
        $issuedAt = now();

        for ($i = 0; $i < $lineItem->quantity; $i++) {
            $serial = $this->ticketSerialService->nextSerial($event);
            $ticketNumber = $this->ticketNumberGenerator->nextForEvent($event);
            $qrToken = $this->qrTokenGenerator->generate();

            $created[] = Ticket::query()->create([
                'venue_id' => $order->venue_id,
                'event_id' => $order->event_id,
                'order_id' => $order->id,
                'ticket_type_id' => $ticketType->id,
                'serial' => $serial,
                'ticket_number' => $ticketNumber,
                'qr_token' => $qrToken,
                'issued_at' => $issuedAt,
                'qr_code_path' => $this->qrCodePath($qrToken),
                'status' => TicketStatus::Issued,
            ]);
        }

        return $created;
    }

    private function qrCodePath(string $qrToken): string
    {
        // Regenerable storage artifact — qr_token is the source of truth (Phase 8.3.3).
        return "tickets/qr/{$qrToken}.png";
    }
}
