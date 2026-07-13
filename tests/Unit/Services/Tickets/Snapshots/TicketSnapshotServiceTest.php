<?php

namespace Tests\Unit\Services\Tickets\Snapshots;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketSnapshot;
use App\Models\TicketType;
use App\Models\Venue;
use App\Services\Tickets\Snapshots\TicketSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_captures_an_immutable_json_snapshot_for_a_ticket(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Summer Fest',
            'start_datetime' => '2026-08-01 20:00:00',
            'end_datetime' => '2026-08-02 02:00:00',
        ]);

        $ticketType = TicketType::factory()->forEvent($event)->create([
            'name' => 'VIP',
            'color' => '#FF0000',
            'price' => 150,
        ]);

        $order = Order::factory()->forEvent($event)->paid()->create([
            'venue_id' => $venue->id,
            'customer_name' => 'Layla Hassan',
        ]);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 1,
            'unit_price' => '150.00',
        ]);

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'ticket_number' => 'EV000001-260801-000001',
            'issued_at' => now(),
        ]);

        $order->load([
            'orderItems.ticketType',
            'event.venue',
            'paymentAccount',
            'reservations.tableSeat.venueTable',
        ]);

        $snapshot = app(TicketSnapshotService::class)->captureForTicket($ticket, $order);

        $this->assertSame($ticket->id, $snapshot->ticket_id);
        $this->assertSame('Summer Fest', $snapshot->payload['event']['name']);
        $this->assertSame($venue->name, $snapshot->payload['venue']['name']);
        $this->assertSame('VIP', $snapshot->payload['ticket_type']['name']);
        $this->assertSame('#FF0000', $snapshot->payload['ticket_type']['color']);
        $this->assertSame('Layla Hassan', $snapshot->payload['holder']['name']);
        $this->assertSame($order->customer_email, $snapshot->payload['holder']['email']);
        $this->assertSame('150.00', $snapshot->payload['price']['amount']);
        $this->assertSame('EV000001-260801-000001', $snapshot->payload['ticket']['number']);
    }

    #[Test]
    public function it_is_idempotent_when_a_snapshot_already_exists(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $order = Order::factory()->forEvent($event)->paid()->create(['venue_id' => $venue->id]);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 1,
            'unit_price' => '50.00',
        ]);

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create([
            'payload' => ['event' => ['name' => 'Frozen Name']],
        ]);

        $order->load(['orderItems.ticketType', 'event.venue', 'paymentAccount', 'reservations.tableSeat.venueTable']);

        $snapshot = app(TicketSnapshotService::class)->captureForTicket($ticket, $order);

        $this->assertSame('Frozen Name', $snapshot->payload['event']['name']);
        $this->assertSame(1, TicketSnapshot::query()->where('ticket_id', $ticket->id)->count());
    }
}
