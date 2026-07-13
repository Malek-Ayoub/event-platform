<?php

namespace Tests\Feature\Orders;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Contracts\Tickets\TicketPdfRendererInterface;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Event;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use App\Models\TicketType;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\OrderService;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Tickets\FilesystemTicketPdfStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\Support\Tickets\RecordingQrImageGenerator;
use Tests\Support\Tickets\RecordingTicketPdfRenderer;
use Tests\TestCase;

class GeneratePdfOnQrGeneratedConsumerTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    private RecordingQrImageGenerator $qrGenerator;

    private RecordingTicketPdfRenderer $pdfRenderer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'tickets.qr.disk' => 'local',
            'tickets.pdf.disk' => 'local',
        ]);

        $this->qrGenerator = new RecordingQrImageGenerator;
        $this->pdfRenderer = new RecordingTicketPdfRenderer;

        $this->app->instance(QrImageGeneratorInterface::class, $this->qrGenerator);
        $this->app->instance(TicketPdfRendererInterface::class, $this->pdfRenderer);
    }

    #[Test]
    public function it_generates_pdf_artifacts_when_qr_generated_events_are_processed(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10]);

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

        app(OutboxDispatcher::class)->dispatchPending();
        app(OutboxDispatcher::class)->dispatchPending();
        app(OutboxDispatcher::class)->dispatchPending();

        $tickets = Ticket::query()->where('order_id', $order->id)->get();
        $this->assertCount(2, $tickets);
        $this->assertSame(2, OutboxEvent::query()->where('event_type', 'ticket.qr_generated')->count());
        $this->assertSame(2, OutboxEvent::query()->where('event_type', 'ticket.pdf_generated')->count());
        $this->assertSame(2, count($this->pdfRenderer->calls));

        $storage = app(FilesystemTicketPdfStorage::class);

        foreach ($tickets as $ticket) {
            $path = $storage->pathForTicket($ticket, 1);
            $this->assertTrue(Storage::disk('local')->exists($path));

            $artifact = TicketArtifact::query()
                ->where('ticket_id', $ticket->id)
                ->where('type', TicketArtifactType::Pdf)
                ->first();

            $this->assertNotNull($artifact);
            $this->assertSame(TicketArtifactStatus::Ready, $artifact->status);
            $this->assertSame($path, $artifact->path);
            $this->assertSame(1, $artifact->version);
            $this->assertSame('application/pdf', $artifact->mime_type);
            $this->assertNotNull($artifact->checksum);
        }
    }

    #[Test]
    public function it_does_not_regenerate_pdfs_when_the_consumer_is_retried(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 10]);

        $order = app(OrderService::class)->createOrder(new CreateOrderData(
            eventId: $event->id,
            customerName: 'Jane Doe',
            customerEmail: 'jane@example.com',
            customerPhone: null,
            customerUserId: null,
            lineItems: [new CreateOrderLineItemData($ticketType->id, 1)],
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

        app(OutboxDispatcher::class)->dispatchPending();
        app(OutboxDispatcher::class)->dispatchPending();
        app(OutboxDispatcher::class)->dispatchPending();

        $qrGeneratedEvent = OutboxEvent::query()->where('event_type', 'ticket.qr_generated')->firstOrFail();
        $callsBeforeRetry = count($this->pdfRenderer->calls);

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($qrGeneratedEvent->id)
            ->update(['status' => OutboxEventStatus::Pending, 'processed_at' => null]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame($callsBeforeRetry, count($this->pdfRenderer->calls));
        $this->assertTrue(
            app(ConsumerReceiptRepository::class)->hasProcessed($qrGeneratedEvent->id, 'tickets.generate_pdf_on_qr_generated'),
        );
    }
}
