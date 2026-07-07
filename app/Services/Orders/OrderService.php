<?php

namespace App\Services\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\ActivityLogService;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private TicketService $ticketService,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function createOrder(CreateOrderData $data): Order
    {
        return $this->transactionRunner->run(function () use ($data): Order {
            $event = Event::query()->findOrFail($data->eventId);

            $totals = $this->calculateTotals($data->lineItems);

            $order = Order::query()->create([
                'venue_id' => $event->venue_id,
                'event_id' => $event->id,
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

            $this->ticketService->createForOrder($order, $event, $data->lineItems);

            if ($data->reservationId !== null) {
                $this->linkReservation($order, $data->reservationId);
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $order,
                action: 'created',
                newValues: [
                    'order_number' => $order->order_number,
                    'status' => $order->status->value,
                    'total' => $order->total,
                ],
                changedFields: ['order_number', 'status', 'total'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'order.created',
                aggregate: $order,
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'event_id' => $order->event_id,
                    'total' => $order->total,
                ],
            );

            return $order->fresh(['tickets']);
        });
    }

    /**
     * @param  list<CreateOrderLineItemData>  $lineItems
     * @return array{subtotal: string, tax_amount: string, discount_amount: string, total: string}
     */
    private function calculateTotals(array $lineItems): array
    {
        $subtotal = '0.00';

        foreach ($lineItems as $lineItem) {
            if ($lineItem->quantity < 1) {
                continue;
            }

            $ticketType = TicketType::query()->findOrFail($lineItem->ticketTypeId);
            $lineTotal = bcmul((string) $ticketType->price, (string) $lineItem->quantity, 2);
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

        $reservation->update(['order_id' => $order->id]);
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.Str::upper(Str::random(10));
    }
}
