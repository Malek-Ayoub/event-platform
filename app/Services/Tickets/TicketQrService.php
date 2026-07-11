<?php

namespace App\Services\Tickets;

use App\Contracts\Tickets\QrImageGeneratorInterface;
use App\Contracts\Tickets\TicketQrStorageInterface;
use App\Models\Ticket;
use App\Services\Tickets\Data\EnsureQrImageResult;
use App\Services\TransactionRunner;
use App\Support\Orders\TicketQrPayload;

/**
 * Ensures a regenerable QR image artifact exists for a ticket (Phase 8.3.3a).
 *
 * qr_token is the source of truth; the PNG is a disposable artifact.
 */
final class TicketQrService
{
    public function __construct(
        private QrImageGeneratorInterface $qrImageGenerator,
        private TicketQrStorageInterface $storage,
        private TransactionRunner $transactionRunner,
    ) {}

    public function ensureQrImage(int $ticketId): EnsureQrImageResult
    {
        $ticket = Ticket::query()->whereKey($ticketId)->firstOrFail();
        $path = $this->storage->pathForToken($ticket->qr_token);

        if ($this->storage->exists($path)) {
            $this->syncQrCodePath($ticket, $path);

            return new EnsureQrImageResult(wasGenerated: false, storagePath: $path);
        }

        $png = $this->qrImageGenerator->generatePng(TicketQrPayload::forTicket($ticket));
        $this->storage->put($path, $png);
        $this->syncQrCodePath($ticket, $path);

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
}
