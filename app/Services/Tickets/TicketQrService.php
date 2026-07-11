<?php

namespace App\Services\Tickets;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Contracts\Tickets\TicketQrStorageInterface;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Ticket;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use App\Services\Tickets\Data\EnsureQrImageResult;
use App\Services\TransactionRunner;
use App\Support\Orders\TicketQrPayload;

/**
 * Ensures a regenerable QR image artifact exists for a ticket (Phase 8.3.3a).
 *
 * qr_token is the source of truth; the PNG is a disposable artifact recorded in ticket_artifacts.
 */
final class TicketQrService
{
    public function __construct(
        private QrImageGeneratorInterface $qrImageGenerator,
        private TicketQrStorageInterface $storage,
        private TicketArtifactService $ticketArtifactService,
        private TransactionRunner $transactionRunner,
    ) {}

    public function ensureQrImage(int $ticketId): EnsureQrImageResult
    {
        $ticket = Ticket::query()->whereKey($ticketId)->firstOrFail();
        $path = $this->storage->pathForToken($ticket->qr_token);
        $disk = (string) config('tickets.qr.disk', 'local');

        if ($this->storage->exists($path)) {
            $this->syncQrCodePath($ticket, $path);
            $this->recordArtifactIfMissing($ticket, $disk, $path, $this->storage->get($path));

            return new EnsureQrImageResult(wasGenerated: false, storagePath: $path);
        }

        $png = $this->qrImageGenerator->generatePng(TicketQrPayload::forTicket($ticket));
        $this->storage->put($path, $png);
        $this->syncQrCodePath($ticket, $path);
        $this->ticketArtifactService->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: $disk,
            path: $path,
            mimeType: 'image/png',
            binaryContents: $png,
        );

        return new EnsureQrImageResult(wasGenerated: true, storagePath: $path);
    }

    private function syncQrCodePath(Ticket $ticket, string $path): void
    {
        if ($ticket->qr_code_path === $path) {
            return;
        }

        $this->transactionRunner->run(function () use ($ticket, $path): void {
            $locked = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $locked->update(['qr_code_path' => $path]);
        });
    }

    private function recordArtifactIfMissing(Ticket $ticket, string $disk, string $path, string $binaryContents): void
    {
        if ($this->ticketArtifactService->findReadyForTicket($ticket, TicketArtifactType::Qr) !== null) {
            return;
        }

        $this->ticketArtifactService->record(
            ticket: $ticket,
            type: TicketArtifactType::Qr,
            disk: $disk,
            path: $path,
            mimeType: 'image/png',
            binaryContents: $binaryContents,
        );
    }
}
