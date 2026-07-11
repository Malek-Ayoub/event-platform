<?php

namespace Tests\Unit\Services\Tickets;

use App\Contracts\Tickets\TicketPdfRendererInterface;
use App\Contracts\Tickets\TicketPdfStorageInterface;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use App\Models\TicketSnapshot;
use App\Models\TicketType;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use App\Services\Tickets\TicketPdfService;
use App\Services\Tickets\TicketQrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Tickets\RecordingQrImageGenerator;
use Tests\Support\Tickets\RecordingTicketPdfRenderer;
use Tests\TestCase;

class TicketPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private RecordingTicketPdfRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'tickets.qr.disk' => 'local',
            'tickets.pdf.disk' => 'local',
        ]);

        $this->renderer = new RecordingTicketPdfRenderer;
        $this->app->instance(TicketPdfRendererInterface::class, $this->renderer);
        $this->app->instance(
            \App\Contracts\Tickets\QrImageGeneratorInterface::class,
            new RecordingQrImageGenerator,
        );
    }

    #[Test]
    public function it_generates_and_stores_a_pdf_for_a_ticket_with_snapshot_and_qr(): void
    {
        $ticket = $this->createTicketWithSnapshot();
        app(TicketQrService::class)->ensureQrImage($ticket->id);

        $expectedPath = app(TicketPdfStorageInterface::class)->pathForTicket($ticket, 1);

        $result = app(TicketPdfService::class)->ensurePdf($ticket->id);

        $this->assertTrue($result->wasGenerated);
        $this->assertSame($expectedPath, $result->storagePath);
        $this->assertTrue(Storage::disk('local')->exists($expectedPath));
        $this->assertCount(1, $this->renderer->calls);

        $artifact = app(TicketArtifactService::class)->findReadyForTicket($ticket->fresh(), TicketArtifactType::Pdf);
        $this->assertNotNull($artifact);
        $this->assertSame(TicketArtifactStatus::Ready, $artifact->status);
        $this->assertSame('application/pdf', $artifact->mime_type);
    }

    #[Test]
    public function it_skips_generation_when_a_ready_pdf_artifact_already_exists(): void
    {
        $ticket = $this->createTicketWithSnapshot();
        app(TicketQrService::class)->ensureQrImage($ticket->id);
        $service = app(TicketPdfService::class);

        $service->ensurePdf($ticket->id);
        $result = $service->ensurePdf($ticket->id);

        $this->assertFalse($result->wasGenerated);
        $this->assertCount(1, $this->renderer->calls);
    }

    #[Test]
    public function it_appends_a_new_pdf_version_instead_of_replacing_the_previous_one(): void
    {
        $ticket = $this->createTicketWithSnapshot();
        app(TicketQrService::class)->ensureQrImage($ticket->id);
        $service = app(TicketPdfService::class);
        $storage = app(TicketPdfStorageInterface::class);

        $first = $service->ensurePdf($ticket->id);
        $this->assertTrue($first->wasGenerated);
        $this->assertSame(1, $first->version);

        Storage::disk('local')->delete($first->storagePath);

        $second = $service->ensurePdf($ticket->id);
        $this->assertTrue($second->wasGenerated);
        $this->assertSame(2, $second->version);
        $this->assertSame($storage->pathForTicket($ticket, 2), $second->storagePath);
        $this->assertFalse(Storage::disk('local')->exists($storage->pathForTicket($ticket, 1)));
        $this->assertTrue(Storage::disk('local')->exists($storage->pathForTicket($ticket, 2)));

        $latest = app(TicketArtifactService::class)->findLatestReady($ticket->fresh(), TicketArtifactType::Pdf);
        $this->assertNotNull($latest);
        $this->assertSame(2, $latest->version);
        $this->assertSame(2, TicketArtifact::query()->where('ticket_id', $ticket->id)->where('type', TicketArtifactType::Pdf)->count());
    }

    private function createTicketWithSnapshot(): Ticket
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id, 'name' => 'PDF Night']);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['name' => 'General']);

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 1,
            'unit_price' => '75.00',
        ]);

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'ticket_number' => 'EV000001-260801-000099',
            'issued_at' => now(),
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create([
            'payload' => [
                'event' => ['name' => 'PDF Night', 'starts_at' => '2026-08-01T20:00:00+00:00', 'ends_at' => null],
                'venue' => ['name' => 'Main Hall'],
                'ticket_type' => ['name' => 'General', 'color' => '#2563eb'],
                'holder' => ['name' => 'Test Holder'],
                'seat' => ['label' => null],
                'price' => ['amount' => '75.00', 'currency' => 'USD'],
                'ticket' => ['number' => $ticket->ticket_number, 'issued_at' => $ticket->issued_at?->toIso8601String()],
            ],
        ]);

        return $ticket;
    }
}
