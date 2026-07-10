<?php

namespace Tests\Unit\Services\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Exceptions\Orders\InsufficientTicketsException;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Orders\Data\ResolvedOrderLineItemData;
use App\Services\Orders\TicketSerialService;
use App\Services\Orders\TicketService;
use App\Services\TransactionRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_reserves_inventory_for_an_order_line_item(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['price' => 50, 'quantity' => 10]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Pending]);

        app(TransactionRunner::class)->run(fn () => app(TicketService::class)->reserveInventoryForOrder(
            $order,
            $event,
            [$this->resolvedLineItem($ticketType, 1)],
        ));

        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
        $this->assertSame(0, Ticket::query()->count());
    }

    #[Test]
    public function it_issues_a_single_ticket_for_a_reserved_line_item(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['price' => 50, 'quantity' => 10, 'quantity_sold' => 1]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);

        $runner = app(TransactionRunner::class);
        $tickets = $runner->run(fn () => app(TicketService::class)->issueForOrder(
            $order,
            $event,
            [$this->resolvedLineItem($ticketType, 1)],
        ));

        $this->assertCount(1, $tickets);
        $this->assertSame($order->id, $tickets[0]->order_id);
        $this->assertSame('000001', $tickets[0]->serial);
        $this->assertSame(TicketStatus::Issued, $tickets[0]->status);
        $this->assertNotNull($tickets[0]->ticket_number);
        $this->assertNotNull($tickets[0]->qr_token);
        $this->assertNotNull($tickets[0]->issued_at);
        $this->assertSame('tickets/qr/'.$tickets[0]->qr_token.'.png', $tickets[0]->qr_code_path);
        $this->assertStringStartsWith('EV', $tickets[0]->ticket_number);
        $this->assertSame(1, $ticketType->fresh()->quantity_sold);
    }

    #[Test]
    public function it_issues_multiple_tickets_with_unique_serials(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10, 'quantity_sold' => 3]);
        $order = Order::factory()->forEvent($event)->create();

        $runner = app(TransactionRunner::class);
        $tickets = $runner->run(fn () => app(TicketService::class)->issueForOrder(
            $order,
            $event,
            [$this->resolvedLineItem($ticketType, 3)],
        ));

        $this->assertCount(3, $tickets);
        $serials = collect($tickets)->pluck('serial')->all();
        $this->assertSame(['000001', '000002', '000003'], $serials);
        $this->assertSame(3, count(array_unique($serials)));
    }

    #[Test]
    public function ticket_issuance_failure_rolls_back_all_tickets(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10, 'quantity_sold' => 2]);
        $order = Order::factory()->forEvent($event)->create();

        $this->mock(TicketSerialService::class, function ($mock): void {
            $mock->shouldReceive('nextSerial')->once()->andReturn('000001');
            $mock->shouldReceive('nextSerial')->once()->andThrow(new RuntimeException('serial failed'));
        });

        $runner = app(TransactionRunner::class);

        try {
            $runner->run(fn () => app(TicketService::class)->issueForOrder(
                $order,
                $event,
                [$this->resolvedLineItem($ticketType, 2)],
            ));
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(2, $ticketType->fresh()->quantity_sold);
    }

    #[Test]
    public function it_throws_when_insufficient_tickets_available_for_reservation(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity' => 2,
            'quantity_sold' => 1,
        ]);
        $order = Order::factory()->forEvent($event)->create();

        $this->expectException(InsufficientTicketsException::class);

        app(TransactionRunner::class)->run(fn () => app(TicketService::class)->reserveInventoryForOrder(
            $order,
            $event,
            [$this->resolvedLineItem($ticketType, 2)],
        ));
    }

    private function resolvedLineItem(TicketType $ticketType, int $quantity): ResolvedOrderLineItemData
    {
        return new ResolvedOrderLineItemData(
            ticketType: $ticketType,
            quantity: $quantity,
            unitPrice: number_format((float) $ticketType->price, 2, '.', ''),
        );
    }
}
