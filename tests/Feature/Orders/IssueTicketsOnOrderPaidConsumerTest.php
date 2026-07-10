<?php

namespace Tests\Feature\Orders;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\OrderService;
use App\Services\Outbox\OutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class IssueTicketsOnOrderPaidConsumerTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    #[Test]
    public function it_issues_tickets_when_processing_an_order_paid_outbox_event(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 100,
            'quantity' => 10,
        ]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, 2)],
            actor: $owner,
        ));

        $order->update(['status' => OrderStatus::Paid]);

        $outbox = OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_transaction_id' => null,
                ],
            ],
        ]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertTrue(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'tickets.issue_on_order_paid'),
        );
    }

    #[Test]
    public function it_does_not_issue_duplicate_tickets_when_the_consumer_is_retried(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10, 'quantity_sold' => 2]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 2,
            'unit_price' => '100.00',
        ]);

        $outbox = OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                ],
            ],
        ]);

        app(OutboxDispatcher::class)->dispatchPending();

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($outbox->id)
            ->update(['status' => OutboxEventStatus::Pending, 'processed_at' => null]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function it_issues_tickets_before_order_paid_email_is_sent(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Summer Fest',
        ]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 60,
            'quantity' => 10,
        ]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Alex Buyer',
            customerEmail: 'alex@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, 2)],
            actor: $owner,
        ));

        $order->update(['status' => OrderStatus::Paid]);

        PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'venue_id' => $venue->id,
            'amount' => $order->total,
        ]);

        OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_transaction_id' => PaymentTransaction::query()->value('id'),
                ],
            ],
        ]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
    }
}
