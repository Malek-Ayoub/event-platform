<?php

namespace Tests\Feature\Orders;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\TicketType;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Orders\Data\CreateOrderData;
use App\Services\Orders\Data\CreateOrderLineItemData;
use App\Services\Orders\OrderService;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Tickets\FilesystemTicketQrStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\Support\Tickets\RecordingQrImageGenerator;
use Tests\TestCase;

class GenerateQrOnTicketIssuedConsumerTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    private RecordingQrImageGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['tickets.qr.disk' => 'local']);

        $this->generator = new RecordingQrImageGenerator;
        $this->app->instance(QrImageGeneratorInterface::class, $this->generator);
    }

    #[Test]
    public function it_generates_qr_images_when_ticket_issued_events_are_processed(): void
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

        $tickets = Ticket::query()->where('order_id', $order->id)->get();
        $this->assertCount(2, $tickets);
        $this->assertSame(2, OutboxEvent::query()->where('event_type', 'ticket.issued')->count());

        app(OutboxDispatcher::class)->dispatchPending();

        $storage = app(FilesystemTicketQrStorage::class);

        foreach ($tickets as $ticket) {
            $path = $storage->pathForToken($ticket->qr_token);
            $this->assertTrue(Storage::disk('local')->exists($path));
            $this->assertSame($path, $ticket->fresh()->qr_code_path);

            $artifact = TicketArtifact::query()
                ->where('ticket_id', $ticket->id)
                ->where('type', TicketArtifactType::Qr)
                ->first();

            $this->assertNotNull($artifact);
            $this->assertSame(TicketArtifactStatus::Ready, $artifact->status);
            $this->assertSame($path, $artifact->path);
            $this->assertSame('image/png', $artifact->mime_type);
            $this->assertNotNull($artifact->checksum);
        }

        $this->assertSame(2, $this->generator->calls);
        $this->assertSame(2, OutboxEvent::query()->where('event_type', 'ticket.qr_generated')->count());
    }

    #[Test]
    public function it_does_not_regenerate_qr_images_when_the_consumer_is_retried(): void
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

        $ticket = Ticket::query()->where('order_id', $order->id)->firstOrFail();
        $issuedEvent = OutboxEvent::query()->where('event_type', 'ticket.issued')->firstOrFail();

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($issuedEvent->id)
            ->update(['status' => OutboxEventStatus::Pending, 'processed_at' => null]);

        $originalToken = $ticket->qr_token;
        $callsBeforeRetry = $this->generator->calls;

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame($callsBeforeRetry, $this->generator->calls);
        $this->assertSame($originalToken, $ticket->fresh()->qr_token);
        $this->assertTrue(
            app(ConsumerReceiptRepository::class)->hasProcessed($issuedEvent->id, 'tickets.generate_qr_on_issued'),
        );
    }
}
