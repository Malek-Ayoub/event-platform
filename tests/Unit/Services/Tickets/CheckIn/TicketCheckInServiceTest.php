<?php

namespace Tests\Unit\Services\Tickets\CheckIn;

use App\Enums\OrdersDomain\TicketStatus;
use App\Exceptions\Tickets\TicketCheckInRejectedException;
use App\Exceptions\Tickets\TicketNotFoundException;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\TicketSnapshot;
use App\Models\TicketType;
use App\Services\Orders\QrTokenGenerator;
use App\Services\Tickets\CheckIn\Data\CheckInTicketData;
use App\Services\Tickets\CheckIn\TicketCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketCheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_checks_in_a_valid_ticket_and_publishes_ticket_checked_in(): void
    {
        ['ticket' => $ticket, 'staff' => $staff] = $this->createIssuedTicket();

        $result = app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
            gateId: 7,
            deviceId: 'scanner-1',
        ));

        $this->assertTrue($result->valid);
        $this->assertSame('EV000001-260801-000001', $result->ticketNumber);
        $this->assertSame('Layla Hassan', $result->holderName);
        $this->assertSame('Summer Fest', $result->eventName);
        $this->assertSame(TicketStatus::CheckedIn, $result->status);

        $ticket->refresh();
        $this->assertSame(TicketStatus::CheckedIn, $ticket->status);
        $this->assertSame(1, TicketCheckIn::query()->where('ticket_id', $ticket->id)->count());

        $event = OutboxEvent::query()->where('event_type', 'ticket.checked_in')->firstOrFail();
        $this->assertSame($ticket->id, data_get($event->payload, 'payload.ticket_id'));
        $this->assertSame($ticket->event_id, data_get($event->payload, 'payload.event_id'));
        $this->assertSame($ticket->venue_id, data_get($event->payload, 'payload.venue_id'));
        $this->assertSame($staff->id, data_get($event->payload, 'payload.checked_in_by_user_id'));
        $this->assertSame(7, data_get($event->payload, 'payload.gate_id'));
    }

    #[Test]
    public function it_rejects_unknown_qr_tokens(): void
    {
        ['staff' => $staff] = $this->createIssuedTicket();

        $this->expectException(TicketNotFoundException::class);

        app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: app(QrTokenGenerator::class)->generate(),
            checkedInByUserId: $staff->id,
        ));
    }

    #[Test]
    public function it_rejects_refunded_tickets(): void
    {
        ['ticket' => $ticket, 'staff' => $staff] = $this->createIssuedTicket(TicketStatus::Refunded);

        $this->expectException(TicketCheckInRejectedException::class);
        $this->expectExceptionMessage('refunded');

        app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
        ));
    }

    #[Test]
    public function it_rejects_cancelled_tickets(): void
    {
        ['ticket' => $ticket, 'staff' => $staff] = $this->createIssuedTicket(TicketStatus::Cancelled);

        $this->expectException(TicketCheckInRejectedException::class);
        $this->expectExceptionMessage('cancelled');

        app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
        ));
    }

    #[Test]
    public function it_rejects_invalidated_tickets(): void
    {
        ['ticket' => $ticket, 'staff' => $staff] = $this->createIssuedTicket(TicketStatus::Invalidated);

        $this->expectException(TicketCheckInRejectedException::class);
        $this->expectExceptionMessage('invalidated');

        app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
        ));
    }

    #[Test]
    public function it_rejects_already_checked_in_tickets(): void
    {
        ['ticket' => $ticket, 'staff' => $staff] = $this->createIssuedTicket(TicketStatus::CheckedIn);

        $this->expectException(TicketCheckInRejectedException::class);
        $this->expectExceptionMessage('already been checked in');

        app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
        ));
    }

    #[Test]
    public function it_rejects_tickets_for_events_that_have_ended(): void
    {
        ['ticket' => $ticket, 'staff' => $staff, 'event' => $event] = $this->createIssuedTicket();
        $event->update(['end_datetime' => now()->subHour()]);

        $this->expectException(TicketCheckInRejectedException::class);
        $this->expectExceptionMessage('event for this ticket has already ended');

        app(TicketCheckInService::class)->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
        ));
    }

    #[Test]
    public function it_prevents_double_check_in_under_row_lock(): void
    {
        ['ticket' => $ticket, 'staff' => $staff] = $this->createIssuedTicket();
        $service = app(TicketCheckInService::class);

        $service->checkIn(new CheckInTicketData(
            qrToken: $ticket->qr_token,
            checkedInByUserId: $staff->id,
        ));

        try {
            $service->checkIn(new CheckInTicketData(
                qrToken: $ticket->qr_token,
                checkedInByUserId: $staff->id,
            ));
            $this->fail('Expected second check-in to be rejected.');
        } catch (TicketCheckInRejectedException $exception) {
            $this->assertSame('Ticket has already been checked in.', $exception->getMessage());
        }

        $this->assertSame(1, TicketCheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'ticket.checked_in')->count());
    }

    /**
     * @return array{ticket: Ticket, staff: \App\Models\User, event: Event}
     */
    private function createIssuedTicket(?TicketStatus $status = null): array
    {
        ['venue' => $venue, 'user' => $staff] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Summer Fest',
            'end_datetime' => now()->addDay(),
        ]);

        $order = Order::factory()->forEvent($event)->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create();

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'ticket_number' => 'EV000001-260801-000001',
            'status' => $status ?? TicketStatus::Issued,
            'checked_in_at' => $status === TicketStatus::CheckedIn ? now() : null,
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create([
            'payload' => [
                'event' => ['name' => 'Summer Fest', 'starts_at' => now()->toIso8601String(), 'ends_at' => null],
                'venue' => ['name' => 'Main Hall'],
                'ticket_type' => ['name' => 'GA', 'color' => null],
                'holder' => ['name' => 'Layla Hassan', 'email' => 'layla@example.com'],
                'seat' => ['label' => null],
                'price' => ['amount' => '50.00', 'currency' => 'USD'],
                'ticket' => ['number' => $ticket->ticket_number, 'issued_at' => now()->toIso8601String()],
            ],
        ]);

        return ['ticket' => $ticket, 'staff' => $staff, 'event' => $event];
    }
}
