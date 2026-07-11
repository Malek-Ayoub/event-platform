<?php

namespace Tests\Unit\Services\Tickets;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Contracts\Tickets\TicketQrStorageInterface;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Tickets\FilesystemTicketQrStorage;
use App\Services\Tickets\TicketQrService;
use App\Support\Orders\TicketQrPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Tickets\RecordingQrImageGenerator;
use Tests\TestCase;

class TicketQrServiceTest extends TestCase
{
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
    public function it_generates_and_stores_a_qr_image_for_a_ticket(): void
    {
        $ticket = $this->createIssuedTicket();
        $expectedPath = app(TicketQrStorageInterface::class)->pathForToken($ticket->qr_token);

        $result = app(TicketQrService::class)->ensureQrImage($ticket->id);

        $this->assertTrue($result->wasGenerated);
        $this->assertSame($expectedPath, $result->storagePath);
        $this->assertTrue(Storage::disk('local')->exists($expectedPath));
        $this->assertSame($expectedPath, $ticket->fresh()->qr_code_path);
        $this->assertSame(1, $this->generator->calls);
        $this->assertSame([$ticket->qr_token], $this->generator->payloads);
    }

    #[Test]
    public function it_skips_generation_when_the_qr_file_already_exists(): void
    {
        $ticket = $this->createIssuedTicket();
        $storage = app(TicketQrStorageInterface::class);
        $path = $storage->pathForToken($ticket->qr_token);
        Storage::disk('local')->put($path, 'existing-png');

        $result = app(TicketQrService::class)->ensureQrImage($ticket->id);

        $this->assertFalse($result->wasGenerated);
        $this->assertSame(0, $this->generator->calls);
        $this->assertSame($path, $ticket->fresh()->qr_code_path);
    }

    #[Test]
    public function it_regenerates_the_qr_image_when_the_file_was_deleted(): void
    {
        $ticket = $this->createIssuedTicket();
        $originalToken = $ticket->qr_token;
        $storage = app(FilesystemTicketQrStorage::class);

        app(TicketQrService::class)->ensureQrImage($ticket->id);

        $path = $storage->pathForToken($originalToken);
        Storage::disk('local')->delete($path);

        $result = app(TicketQrService::class)->ensureQrImage($ticket->id);

        $this->assertTrue($result->wasGenerated);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame($originalToken, $ticket->fresh()->qr_token);
        $this->assertSame($originalToken, TicketQrPayload::forTicket($ticket->fresh()));
        $this->assertSame(2, $this->generator->calls);
    }

    private function createIssuedTicket(): Ticket
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create(['status' => OrderStatus::Paid]);
        $ticketType = TicketType::factory()->forEvent($event)->create();

        OrderItem::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'quantity' => 1,
            'unit_price' => '50.00',
        ]);

        return Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'qr_code_path' => null,
        ]);
    }
}
