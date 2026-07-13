<?php

namespace Tests\Unit\Services\Tickets;

use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Mail\TicketIssuedMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Models\TicketSnapshot;
use App\Models\TicketType;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use App\Services\Tickets\TicketEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TicketEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.ticket_email.enabled' => true,
            'notifications.ticket_email.queue' => false,
            'notifications.ticket_email.from_email' => 'tickets@example.com',
            'notifications.ticket_email.from_name' => 'Event Platform',
        ]);

        Mail::fake();
    }

    #[Test]
    public function it_sends_ticket_email_with_latest_ready_pdf_attachment(): void
    {
        $ticket = $this->createTicketWithSnapshot('layla@example.com');
        $service = app(TicketArtifactService::class);

        $service->appendVersion(
            ticket: $ticket,
            type: TicketArtifactType::Pdf,
            disk: 'local',
            path: 'tickets/pdf/'.$ticket->id.'/v1.pdf',
            mimeType: 'application/pdf',
            binaryContents: 'pdf-v1',
            version: 1,
        );

        $service->appendVersion(
            ticket: $ticket,
            type: TicketArtifactType::Pdf,
            disk: 'local',
            path: 'tickets/pdf/'.$ticket->id.'/v2.pdf',
            mimeType: 'application/pdf',
            binaryContents: 'pdf-v2',
            version: 2,
        );

        $result = app(TicketEmailService::class)->send($ticket->id);

        $this->assertTrue($result->wasSent);

        Mail::assertSent(TicketIssuedMail::class, function (TicketIssuedMail $mail) use ($ticket): bool {
            return count($mail->attachments()) === 1
                && data_get($mail->snapshot->payload, 'event.name') === 'Snapshot Night'
                && data_get($mail->snapshot->payload, 'holder.email') === 'layla@example.com';
        });

        $emailSent = OutboxEvent::query()->where('event_type', 'ticket.email_sent')->firstOrFail();
        $this->assertSame($ticket->id, data_get($emailSent->payload, 'payload.ticket_id'));
        $this->assertSame(2, data_get($emailSent->payload, 'payload.artifact_version'));
        $this->assertSame('layla@example.com', data_get($emailSent->payload, 'payload.recipient'));
        $this->assertNotNull(data_get($emailSent->payload, 'payload.sent_at'));

        Mail::assertSentCount(1);
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'ticket.email_sent')->count());
    }

    #[Test]
    public function it_publishes_ticket_email_sent_only_after_successful_delivery(): void
    {
        $ticket = $this->createTicketWithSnapshot('layla@example.com');

        app(TicketArtifactService::class)->appendVersion(
            ticket: $ticket,
            type: TicketArtifactType::Pdf,
            disk: 'local',
            path: 'tickets/pdf/'.$ticket->id.'/v1.pdf',
            mimeType: 'application/pdf',
            binaryContents: 'pdf-v1',
            version: 1,
        );

        app(TicketEmailService::class)->send($ticket->id);

        $emailSent = OutboxEvent::query()->where('event_type', 'ticket.email_sent')->firstOrFail();
        $this->assertSame($ticket->id, data_get($emailSent->payload, 'payload.ticket_id'));
        $this->assertSame(1, data_get($emailSent->payload, 'payload.artifact_version'));
        $this->assertSame('layla@example.com', data_get($emailSent->payload, 'payload.recipient'));
        $this->assertNotNull(data_get($emailSent->payload, 'payload.sent_at'));
    }

    #[Test]
    public function it_throws_when_ready_pdf_artifact_is_missing(): void
    {
        $ticket = $this->createTicketWithSnapshot('layla@example.com');

        try {
            app(TicketEmailService::class)->send($ticket->id);
            $this->fail('Expected RuntimeException when PDF artifact is missing.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Ready PDF artifact is required before email delivery.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(0, OutboxEvent::query()->where('event_type', 'ticket.email_sent')->count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_does_not_send_when_ticket_email_is_disabled(): void
    {
        config(['notifications.ticket_email.enabled' => false]);

        $ticket = $this->createTicketWithSnapshot('layla@example.com');

        app(TicketArtifactService::class)->appendVersion(
            ticket: $ticket,
            type: TicketArtifactType::Pdf,
            disk: 'local',
            path: 'tickets/pdf/'.$ticket->id.'/v1.pdf',
            mimeType: 'application/pdf',
            binaryContents: 'pdf-v1',
            version: 1,
        );

        $result = app(TicketEmailService::class)->send($ticket->id);

        $this->assertFalse($result->wasSent);
        Mail::assertNothingSent();
        $this->assertSame(0, OutboxEvent::query()->where('event_type', 'ticket.email_sent')->count());
    }

    private function createTicketWithSnapshot(string $email): Ticket
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'status' => OrderStatus::Paid,
            'customer_email' => $email,
            'customer_name' => 'Layla Hassan',
        ]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['name' => 'VIP']);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 1,
            'unit_price' => '100.00',
        ]);

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'ticket_number' => 'EV000001-260801-000010',
            'issued_at' => now(),
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create([
            'payload' => [
                'event' => ['name' => 'Snapshot Night', 'starts_at' => '2026-08-01T20:00:00+00:00', 'ends_at' => null],
                'venue' => ['name' => 'Main Hall'],
                'ticket_type' => ['name' => 'VIP', 'color' => '#2563eb'],
                'holder' => ['name' => 'Layla Hassan', 'email' => $email],
                'seat' => ['label' => null],
                'price' => ['amount' => '100.00', 'currency' => 'USD'],
                'ticket' => ['number' => $ticket->ticket_number, 'issued_at' => $ticket->issued_at?->toIso8601String()],
            ],
        ]);

        return $ticket;
    }
}
