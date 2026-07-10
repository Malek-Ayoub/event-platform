<?php

namespace App\Services\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Orders\OrderNotEligibleForTicketIssuanceException;
use App\Models\Order;
use App\Models\TicketType;
use App\Services\Orders\Data\IssuedTicketsResult;
use App\Services\Orders\Data\ResolvedOrderLineItemData;
use App\Services\TransactionRunner;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Issues tickets for paid orders (Phase 8.3.1).
 *
 * Idempotent: completes only when issued count matches order item quantities.
 * Supports resuming partial issuance after an interrupted run.
 */
final class IssueTicketsService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private TicketService $ticketService,
    ) {}

    public function issueForPaidOrder(int $orderId): IssuedTicketsResult
    {
        return $this->transactionRunner->run(function () use ($orderId): IssuedTicketsResult {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if ($order->status !== OrderStatus::Paid) {
                throw OrderNotEligibleForTicketIssuanceException::forOrder($orderId, $order->status);
            }

            $order->load(['orderItems.ticketType', 'event', 'tickets']);

            $event = $order->event;

            if ($event === null) {
                throw new InvalidArgumentException("Order {$orderId} is missing event.");
            }

            $expectedTotal = (int) $order->orderItems->sum('quantity');

            if ($expectedTotal === 0) {
                return new IssuedTicketsResult([], newlyIssued: false);
            }

            $existingTickets = $order->tickets->all();
            $issuedTotal = count($existingTickets);

            if ($issuedTotal >= $expectedTotal) {
                return new IssuedTicketsResult($existingTickets, newlyIssued: false);
            }

            $remainingLineItems = $this->remainingLineItems($order);

            if ($remainingLineItems === []) {
                return new IssuedTicketsResult($existingTickets, newlyIssued: false);
            }

            $newTickets = $this->ticketService->issueForOrder($order, $event, $remainingLineItems);

            return new IssuedTicketsResult(
                array_merge($existingTickets, $newTickets),
                newlyIssued: true,
            );
        });
    }

    /**
     * @return list<ResolvedOrderLineItemData>
     */
    private function remainingLineItems(Order $order): array
    {
        /** @var Collection<int, int> $expectedByType */
        $expectedByType = $order->orderItems
            ->groupBy('ticket_type_id')
            ->map(fn (Collection $items): int => (int) $items->sum('quantity'));

        /** @var Collection<int, int> $issuedByType */
        $issuedByType = $order->tickets
            ->groupBy('ticket_type_id')
            ->map(fn (Collection $tickets): int => $tickets->count());

        $lineItems = [];

        foreach ($expectedByType as $ticketTypeId => $expectedQuantity) {
            $issuedQuantity = (int) ($issuedByType[$ticketTypeId] ?? 0);
            $remainingQuantity = $expectedQuantity - $issuedQuantity;

            if ($remainingQuantity < 1) {
                continue;
            }

            $ticketType = $this->resolveTicketType($order, (int) $ticketTypeId);
            $unitPrice = $this->resolveUnitPrice($order, (int) $ticketTypeId);

            $lineItems[] = new ResolvedOrderLineItemData(
                ticketType: $ticketType,
                quantity: $remainingQuantity,
                unitPrice: $unitPrice,
            );
        }

        return $lineItems;
    }

    private function resolveTicketType(Order $order, int $ticketTypeId): TicketType
    {
        $orderItem = $order->orderItems->firstWhere('ticket_type_id', $ticketTypeId);

        if ($orderItem?->ticketType === null) {
            throw new InvalidArgumentException(
                "Order {$order->id} is missing ticket type {$ticketTypeId} on order items.",
            );
        }

        return $orderItem->ticketType;
    }

    private function resolveUnitPrice(Order $order, int $ticketTypeId): string
    {
        $orderItem = $order->orderItems->firstWhere('ticket_type_id', $ticketTypeId);

        if ($orderItem === null) {
            throw new InvalidArgumentException(
                "Order {$order->id} is missing ticket type {$ticketTypeId} on order items.",
            );
        }

        return number_format((float) $orderItem->unit_price, 2, '.', '');
    }
}
