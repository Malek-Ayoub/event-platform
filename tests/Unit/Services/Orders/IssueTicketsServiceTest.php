<?php

namespace Tests\Unit\Services\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Orders\OrderNotEligibleForTicketIssuanceException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Models\TicketSerialCounter;
use App\Models\TicketType;
use App\Services\Orders\IssueTicketsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class IssueTicketsServiceTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    #[Test]
    public function it_issues_one_ticket_per_reserved_quantity(): void
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

        $result = app(IssueTicketsService::class)->issueForPaidOrder($order->id);

        $this->assertTrue($result->newlyIssued);
        $this->assertCount(2, $result->tickets);
        $this->assertSame(2, Ticket::query()->where('order_id', $order->id)->count());
        $this->assertSame(2, OutboxEvent::query()->where('event_type', 'ticket.issued')->count());
    }

    #[Test]
    public function it_is_idempotent_when_expected_ticket_count_is_already_issued(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10, 'quantity_sold' => 1]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 1,
            'unit_price' => '100.00',
        ]);

        Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
        ]);

        $result = app(IssueTicketsService::class)->issueForPaidOrder($order->id);

        $this->assertFalse($result->newlyIssued);
        $this->assertCount(1, $result->tickets);
        $this->assertSame(1, Ticket::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function it_completes_partial_issuance_when_fewer_tickets_exist_than_expected(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10, 'quantity_sold' => 3]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 3,
            'unit_price' => '100.00',
        ]);

        Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'serial' => '000001',
        ]);

        TicketSerialCounter::factory()->forEvent($event)->create(['last_serial' => 1]);

        $result = app(IssueTicketsService::class)->issueForPaidOrder($order->id);

        $this->assertTrue($result->newlyIssued);
        $this->assertCount(3, $result->tickets);
        $this->assertSame(3, Ticket::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function it_rejects_ticket_issuance_for_non_paid_orders(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Pending]);

        $this->expectException(OrderNotEligibleForTicketIssuanceException::class);

        app(IssueTicketsService::class)->issueForPaidOrder($order->id);
    }
}
