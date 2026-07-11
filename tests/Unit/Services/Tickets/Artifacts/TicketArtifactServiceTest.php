<?php

namespace Tests\Unit\Services\Tickets\Artifacts;

use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketArtifactServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_an_artifact_with_checksum_and_version(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $ticket = Ticket::factory()->create(['venue_id' => $venue->id]);
        $contents = 'fake-png-binary';

        $artifact = app(TicketArtifactService::class)->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: 'local',
            path: 'tickets/qr/test.png',
            mimeType: 'image/png',
            binaryContents: $contents,
        );

        $this->assertSame($ticket->id, $artifact->ticket_id);
        $this->assertSame(TicketArtifactType::Qr, $artifact->type);
        $this->assertSame(TicketArtifactStatus::Ready, $artifact->status);
        $this->assertSame(1, $artifact->version);
        $this->assertSame(hash('sha256', $contents), $artifact->checksum);
    }

    #[Test]
    public function it_updates_existing_artifact_for_same_ticket_type_and_version(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $ticket = Ticket::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketArtifactService::class);

        $service->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: 'local',
            path: 'tickets/qr/old.png',
            mimeType: 'image/png',
            binaryContents: 'old',
        );

        $service->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: 'local',
            path: 'tickets/qr/new.png',
            mimeType: 'image/png',
            binaryContents: 'new',
        );

        $this->assertSame(1, TicketArtifact::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame('tickets/qr/new.png', $ticket->artifacts()->first()?->path);
        $this->assertSame(hash('sha256', 'new'), $ticket->artifacts()->first()?->checksum);
    }

    #[Test]
    public function find_ready_for_ticket_returns_null_when_artifact_is_not_ready(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $ticket = Ticket::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketArtifactService::class);

        $artifact = $service->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: 'local',
            path: 'tickets/qr/test.png',
            mimeType: 'image/png',
            binaryContents: 'binary',
            status: TicketArtifactStatus::Failed,
        );

        $this->assertNull($service->findReadyForTicket($ticket, TicketArtifactType::Qr));
        $this->assertSame($artifact->id, $service->findForTicket($ticket, TicketArtifactType::Qr)?->id);
    }

    #[Test]
    public function it_can_mark_artifact_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $ticket = Ticket::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketArtifactService::class);

        $artifact = $service->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: 'local',
            path: 'tickets/qr/test.png',
            mimeType: 'image/png',
            binaryContents: 'binary',
            status: TicketArtifactStatus::Generating,
        );

        $updated = $service->markStatus($artifact, TicketArtifactStatus::Ready);

        $this->assertSame(TicketArtifactStatus::Ready, $updated->status);
        $this->assertNotNull($service->findReadyForTicket($ticket, TicketArtifactType::Qr));
    }
}
