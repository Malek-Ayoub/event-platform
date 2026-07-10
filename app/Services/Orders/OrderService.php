<?php

namespace App\Services\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Orders\ReservationAlreadyLinkedException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\ActivityLogService;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\Data\ResolvedOrderLineItemData;
use App\Services\OutboxService;
use App\Services\Payments\PaymentAccountResolver;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private TicketService $ticketService,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
        private PaymentAccountResolver $paymentAccountResolver,
    ) {}

    public function list(int $perPage = 15, ?int $eventId = null, ?OrderStatus $status = null): LengthAwarePaginator
    {
        return Order::query()
            ->when($eventId !== null, fn ($query) => $query->where('event_id', $eventId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getOrder(Order $order): Order
    {
        return Order::query()
            ->whereKey($order->id)
            ->with(['tickets', 'event'])
            ->firstOrFail();
    }

    public function createOrder(CreateOrderData $data): Order
    {
        return $this->transactionRunner->run(function () use ($data): Order {
            $event = Event::query()->findOrFail($data->eventId);
            $paymentAccount = $this->paymentAccountResolver->resolveDefaultForEvent((int) $event->id);

            $resolvedLineItems = $this->resolveLineItems($data->lineItems);
            $totals = $this->calculateTotals($resolvedLineItems);

            $order = Order::query()->create([
                'venue_id' => $event->venue_id,
                'event_id' => $event->id,
                'payment_account_id' => $paymentAccount->id,
                'customer_user_id' => $data->customerUserId,
                'order_number' => $this->generateOrderNumber(),
                'subtotal' => $totals['subtotal'],
                'tax_amount' => $totals['tax_amount'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
                'commission_amount' => 0,
                'coupon_id' => null,
                'promo_code_id' => null,
                'payment_method' => null,
                'payment_reference' => null,
                'status' => OrderStatus::Pending,
                'customer_name' => $data->customerName,
                'customer_email' => $data->customerEmail,
                'customer_phone' => $data->customerPhone,
            ]);

            $this->persistOrderItems($order, $resolvedLineItems);
            $this->ticketService->reserveInventoryForOrder($order, $event, $resolvedLineItems);

            if ($data->reservationId !== null) {
                $this->linkReservation($order, $data->reservationId);
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $order,
                action: 'created',
                newValues: [
                    'order_number' => $order->order_number,
                    'event_id' => $order->event_id,
                    'status' => $order->status->value,
                    'subtotal' => $order->subtotal,
                    'total' => $order->total,
                ],
                changedFields: ['order_number', 'event_id', 'status', 'subtotal', 'total'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'order.created',
                aggregate: $order,
                payload: [
                    'order_number' => $order->order_number,
                    'event_id' => $order->event_id,
                    'subtotal' => $order->subtotal,
                    'total' => $order->total,
                ],
            );

            return $order->fresh(['orderItems']);
        });
    }

    /**
     * @param  list<ResolvedOrderLineItemData>  $lineItems
     */
    private function persistOrderItems(Order $order, array $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            OrderItem::query()->create([
                'venue_id' => $order->venue_id,
                'order_id' => $order->id,
                'ticket_type_id' => $lineItem->ticketType->id,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unitPrice,
            ]);
        }
    }

    /**
     * @param  list<CreateOrderLineItemData>  $lineItems
     * @return list<ResolvedOrderLineItemData>
     */
    private function resolveLineItems(array $lineItems): array
    {
        $resolved = [];

        foreach ($lineItems as $lineItem) {
            if ($lineItem->quantity < 1) {
                continue;
            }

            $ticketType = TicketType::query()
                ->whereKey($lineItem->ticketTypeId)
                ->lockForUpdate()
                ->firstOrFail();

            $resolved[] = new ResolvedOrderLineItemData(
                ticketType: $ticketType,
                quantity: $lineItem->quantity,
                unitPrice: $this->formatPrice($ticketType->price),
            );
        }

        return $resolved;
    }

    /**
     * @param  list<ResolvedOrderLineItemData>  $lineItems
     * @return array{subtotal: string, tax_amount: string, discount_amount: string, total: string}
     */
    private function calculateTotals(array $lineItems): array
    {
        $subtotal = '0.00';

        foreach ($lineItems as $lineItem) {
            $lineTotal = bcmul($lineItem->unitPrice, (string) $lineItem->quantity, 2);
            $subtotal = bcadd($subtotal, $lineTotal, 2);
        }

        $taxAmount = '0.00';
        $discountAmount = '0.00';
        $total = bcsub(bcadd($subtotal, $taxAmount, 2), $discountAmount, 2);

        return [
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ];
    }

    private function linkReservation(Order $order, int $reservationId): void
    {
        $reservation = Reservation::query()
            ->whereKey($reservationId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($reservation->order_id !== null) {
            throw ReservationAlreadyLinkedException::forReservation($reservationId, (int) $reservation->order_id);
        }

        $reservation->update(['order_id' => $order->id]);
    }

    private function formatPrice(mixed $price): string
    {
        return number_format((float) $price, 2, '.', '');
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.Str::upper(Str::random(10));
    }
}
