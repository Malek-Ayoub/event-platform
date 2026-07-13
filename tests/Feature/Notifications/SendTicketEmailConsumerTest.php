<?php

namespace Tests\Feature\Notifications;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Contracts\Tickets\TicketPdfRendererInterface;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Mail\TicketIssuedMail;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Ticket;
use App\Models\TicketSnapshot;
use App\Models\TicketType;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\OrderService;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Tickets\TicketEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\Support\Tickets\RecordingQrImageGenerator;
use Tests\Support\Tickets\RecordingTicketPdfRenderer;
use Tests\TestCase;

class SendTicketEmailConsumerTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'tickets.qr.disk' => 'local',
            'tickets.pdf.disk' => 'local',
            'notifications.ticket_email.enabled' => true,
            'notifications.ticket_email.queue' => false,
            'notifications.ticket_email.from_email' => 'tickets@example.com',
            'notifications.ticket_email.from_name' => 'Event Platform',
        ]);

        $this->app->instance(QrImageGeneratorInterface::class, new RecordingQrImageGenerator);
        $this->app->instance(TicketPdfRendererInterface::class, new RecordingTicketPdfRenderer);

        Mail::fake();
    }

    #[Test]
    public function it_sends_email_after_pdf_generated_events_are_processed(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $order = $this->createPaidOrderWithTickets($venue, $owner, ticketCount: 1);

        $this->dispatchFullTicketPipeline();

        Mail::assertSent(TicketIssuedMail::class, 1);
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'ticket.email_sent')->count());

        $ticket = Ticket::query()->where('order_id', $order->id)->firstOrFail();

        Mail::assertSent(TicketIssuedMail::class, function (TicketIssuedMail $mail) use ($ticket): bool {
            return count($mail->attachments()) === 1
                && data_get($mail->snapshot->payload, 'ticket.number') === $ticket->ticket_number;
        });
    }

    #[Test]
    public function it_does_not_send_duplicate_emails_when_the_consumer_is_retried(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $this->createPaidOrderWithTickets($venue, $owner, ticketCount: 1);
        $this->dispatchFullTicketPipeline();

        $pdfGeneratedEvent = OutboxEvent::query()->where('event_type', 'ticket.pdf_generated')->firstOrFail();

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($pdfGeneratedEvent->id)
            ->update(['status' => OutboxEventStatus::Pending, 'processed_at' => null]);

        app(OutboxDispatcher::class)->dispatchPending();

        Mail::assertSent(TicketIssuedMail::class, 1);
        $this->assertTrue(
            app(ConsumerReceiptRepository::class)->hasProcessed($pdfGeneratedEvent->id, 'notification.email.ticket'),
        );
    }

    #[Test]
    public function it_schedules_retry_when_mail_delivery_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $ticket = $this->createTicketReadyForEmail($venue);

        $outbox = OutboxEvent::factory()->forVenue($venue)->forAggregate($ticket)->create([
            'event_type' => 'ticket.pdf_generated',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'ticket_id' => $ticket->id,
                    'order_id' => $ticket->order_id,
                    'event_id' => $ticket->event_id,
                    'pdf_path' => 'tickets/pdf/'.$ticket->id.'/v1.pdf',
                    'pdf_version' => 1,
                ],
            ],
        ]);

        $this->mock(TicketEmailService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(new RuntimeException('mail transport failed'));
        });

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->failed);
        $this->assertSame(OutboxEventStatus::Pending, $outbox->fresh()->status);
        $this->assertSame(1, $outbox->fresh()->attempts);
        $this->assertFalse(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'notification.email.ticket'),
        );
        $this->assertSame(0, OutboxEvent::query()->where('event_type', 'ticket.email_sent')->count());
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_does_not_send_email_when_pdf_artifact_is_missing(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'status' => OrderStatus::Paid,
            'customer_email' => 'missing-pdf@example.com',
        ]);
        $ticketType = TicketType::factory()->forEvent($event)->create();

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create();

        $outbox = OutboxEvent::factory()->forVenue($venue)->forAggregate($ticket)->create([
            'event_type' => 'ticket.pdf_generated',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'ticket_id' => $ticket->id,
                    'order_id' => $order->id,
                    'event_id' => $event->id,
                    'pdf_path' => 'tickets/pdf/'.$ticket->id.'/v1.pdf',
                    'pdf_version' => 1,
                ],
            ],
        ]);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->failed);
        Mail::assertNothingSent();
        $this->assertSame(0, OutboxEvent::query()->where('event_type', 'ticket.email_sent')->count());
        $this->assertFalse(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'notification.email.ticket'),
        );
    }

    /**
     * @return Order
     */
    private function createPaidOrderWithTickets(\App\Models\Venue $venue, \App\Models\User $owner, int $ticketCount): Order
    {
        $event = Event::factory()->create(['venue_id' => $venue->id, 'name' => 'Email Fest']);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, $ticketCount)],
            actor: $owner,
        ));

        $order->update(['status' => OrderStatus::Paid]);

        OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
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

        return $order;
    }

    private function dispatchFullTicketPipeline(): void
    {
        $this->dispatchUntilPdfGenerated();
        app(OutboxDispatcher::class)->dispatchPending();
    }

    private function dispatchUntilPdfGenerated(): void
    {
        app(OutboxDispatcher::class)->dispatchPending();
        app(OutboxDispatcher::class)->dispatchPending();
        app(OutboxDispatcher::class)->dispatchPending();
    }

    private function createTicketReadyForEmail(\App\Models\Venue $venue): Ticket
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'status' => OrderStatus::Paid,
            'customer_email' => 'retry@example.com',
        ]);
        $ticketType = TicketType::factory()->forEvent($event)->create();

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'ticket_number' => 'EV000001-260801-000020',
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create();
        Storage::disk('local')->put('tickets/pdf/'.$ticket->id.'/v1.pdf', 'pdf-binary');

        app(\App\Services\Tickets\Artifacts\TicketArtifactService::class)->appendVersion(
            ticket: $ticket,
            type: TicketArtifactType::Pdf,
            disk: 'local',
            path: 'tickets/pdf/'.$ticket->id.'/v1.pdf',
            mimeType: 'application/pdf',
            binaryContents: 'pdf-binary',
            version: 1,
        );

        return $ticket;
    }
}
