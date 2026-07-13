<?php

namespace Tests\Feature\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketNumberCounter;
use App\Models\TicketSerialCounter;
use App\Models\TicketType;
use App\Services\Orders\IssueTicketsService;
use App\Support\Orders\TicketQrPayload;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class TicketIdentityIssuanceTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    #[Test]
    public function issued_tickets_have_unique_identity_fields_and_qr_payload_contains_only_qr_token(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 20, 'quantity_sold' => 3]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 3,
            'unit_price' => '100.00',
        ]);

        $result = app(IssueTicketsService::class)->issueForPaidOrder($order->id);

        $ticketNumbers = [];
        $qrTokens = [];

        foreach ($result->tickets as $ticket) {
            $ticketNumbers[] = $ticket->ticket_number;
            $qrTokens[] = $ticket->qr_token;

            $payload = TicketQrPayload::forTicket($ticket);

            $this->assertSame($ticket->qr_token, $payload);
            $this->assertNotSame($ticket->serial, $payload);
            $this->assertNotSame($ticket->ticket_number, $payload);
            $this->assertNotNull($ticket->issued_at);
        }

        $this->assertSame(3, count(array_unique($ticketNumbers)));
        $this->assertSame(3, count(array_unique($qrTokens)));
    }

    #[Test]
    public function duplicate_qr_token_insert_fails_at_the_database_level(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $sharedToken = 'aaaaaaaa-bbbb-4ccc-dddd-eeeeeeeeeeee';

        Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'qr_token' => $sharedToken,
        ]);

        $this->expectException(QueryException::class);

        Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'qr_token' => $sharedToken,
            'ticket_number' => 'TST-DUP-000002',
        ]);
    }

    #[Test]
    public function retry_after_partial_issuance_preserves_existing_ticket_identity(): void
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

        $existing = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'serial' => '000001',
            'ticket_number' => 'EV000001-250712-000099',
            'qr_token' => '11111111-2222-4333-8444-555555555555',
            'issued_at' => now()->subMinute(),
        ]);

        TicketSerialCounter::factory()->forEvent($event)->create(['last_serial' => 1]);
        TicketNumberCounter::factory()->forEvent($event)->create(['last_number' => 99]);

        $result = app(IssueTicketsService::class)->issueForPaidOrder($order->id);

        $this->assertTrue($result->newlyIssued);
        $this->assertSame(3, Ticket::query()->where('order_id', $order->id)->count());

        $unchanged = $existing->fresh();
        $this->assertSame('EV000001-250712-000099', $unchanged->ticket_number);
        $this->assertSame('11111111-2222-4333-8444-555555555555', $unchanged->qr_token);
        $this->assertSame('000001', $unchanged->serial);
    }

    #[Test]
    public function idempotent_retry_does_not_change_any_ticket_identity(): void
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

        $service = app(IssueTicketsService::class);
        $first = $service->issueForPaidOrder($order->id);
        $snapshot = collect($first->tickets)->map(fn (Ticket $ticket) => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'qr_token' => $ticket->qr_token,
            'serial' => $ticket->serial,
        ])->all();

        $second = $service->issueForPaidOrder($order->id);

        $this->assertFalse($second->newlyIssued);
        $this->assertSame($snapshot, collect($second->tickets)->map(fn (Ticket $ticket) => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'qr_token' => $ticket->qr_token,
            'serial' => $ticket->serial,
        ])->all());
    }
}
