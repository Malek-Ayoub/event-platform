<?php

namespace App\Services\Tickets;

use App\Contracts\Tickets\TicketPdfRendererInterface;
use App\Contracts\Tickets\TicketPdfStorageInterface;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use App\Services\Tickets\Data\EnsurePdfResult;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Ensures a regenerable PDF artifact exists for a ticket (Phase 8.3.3b.2).
 */
final class TicketPdfService
{
    public function __construct(
        private TicketPdfRendererInterface $renderer,
        private TicketPdfStorageInterface $storage,
        private TicketArtifactService $ticketArtifactService,
    ) {}

    public function ensurePdf(int $ticketId): EnsurePdfResult
    {
        $ticket = Ticket::query()->with('snapshot')->whereKey($ticketId)->firstOrFail();
        $disk = (string) config('tickets.pdf.disk', 'local');

        if ($ticket->snapshot === null) {
            throw new InvalidArgumentException('Ticket snapshot is required before PDF generation.');
        }

        $existing = $this->ticketArtifactService->findLatestReady($ticket, TicketArtifactType::Pdf);

        if ($existing !== null && $this->artifactFileExists($existing)) {
            return new EnsurePdfResult(
                wasGenerated: false,
                storagePath: $existing->path,
                version: $existing->version,
            );
        }

        $qrArtifact = $this->ticketArtifactService->findLatestReady($ticket, TicketArtifactType::Qr);

        if ($qrArtifact === null) {
            throw new InvalidArgumentException('QR artifact must be ready before PDF generation.');
        }

        $version = $this->ticketArtifactService->nextVersion($ticket, TicketArtifactType::Pdf);
        $path = $this->storage->pathForTicket($ticket, $version);

        $qrBinary = Storage::disk($qrArtifact->disk)->get($qrArtifact->path);
        $pdf = $this->renderer->render($ticket->snapshot->payload, $qrBinary);

        $this->storage->put($path, $pdf);
        $this->ticketArtifactService->appendVersion(
            ticket: $ticket,
            type: TicketArtifactType::Pdf,
            disk: $disk,
            path: $path,
            mimeType: 'application/pdf',
            binaryContents: $pdf,
            version: $version,
        );

        return new EnsurePdfResult(
            wasGenerated: true,
            storagePath: $path,
            version: $version,
        );
    }

    private function artifactFileExists(TicketArtifact $artifact): bool
    {
        return Storage::disk($artifact->disk)->exists($artifact->path);
    }
}
