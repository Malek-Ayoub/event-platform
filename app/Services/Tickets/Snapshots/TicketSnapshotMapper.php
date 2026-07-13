<?php

namespace App\Services\Tickets\Snapshots;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\Venue;

/**
 * Builds immutable JSON snapshot payloads at ticket issuance (Phase 8.3.3b.1).
 */
final class TicketSnapshotMapper
{
    /**
     * @return array<string, mixed>
     */
    public function build(Ticket $ticket, Order $order, Event $event, Venue $venue, OrderItem $orderItem): array
    {
        $ticketType = $orderItem->ticketType;

        return [
            'event' => [
                'name' => (string) $event->name,
                'starts_at' => $event->start_datetime?->toIso8601String(),
                'ends_at' => $event->end_datetime?->toIso8601String(),
            ],
            'venue' => [
                'name' => (string) $venue->name,
            ],
            'ticket_type' => [
                'name' => (string) ($ticketType?->name ?? ''),
                'color' => $ticketType?->color,
            ],
            'holder' => [
                'name' => (string) $order->customer_name,
                'email' => (string) $order->customer_email,
            ],
            'seat' => [
                'label' => $this->resolveSeatLabel($order),
            ],
            'price' => [
                'amount' => number_format((float) $orderItem->unit_price, 2, '.', ''),
                'currency' => $this->resolveCurrency($order),
            ],
            'ticket' => [
                'number' => (string) $ticket->ticket_number,
                'issued_at' => $ticket->issued_at?->toIso8601String(),
            ],
        ];
    }

    private function resolveCurrency(Order $order): string
    {
        $currency = $order->paymentAccount?->currency;

        if (is_string($currency) && $currency !== '') {
            return $currency;
        }

        return (string) config('tickets.snapshot.default_currency', 'USD');
    }

    private function resolveSeatLabel(Order $order): ?string
    {
        $reservation = $order->reservations->first();

        if ($reservation === null) {
            return null;
        }

        $seat = $reservation->tableSeat;
        $table = $seat?->venueTable;

        if ($seat === null) {
            return null;
        }

        if ($table !== null && $table->table_number !== '') {
            return trim($table->table_number.' · '.$seat->seat_number);
        }

        return (string) $seat->seat_number;
    }
}
