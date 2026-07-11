<?php

namespace App\Services\Outbox\Consumers;

use App\Enums\Tickets\TicketArtifactType;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Services\Outbox\AbstractOutboxConsumer;
use App\Services\OutboxService;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use App\Services\Tickets\TicketPdfService;
use App\Services\TransactionRunner;
use InvalidArgumentException;
use RuntimeException;

/**
 * Generates PDF artifacts when a ticket QR is ready (Phase 8.3.3b.2).
 *
 * Listens to ticket.qr_generated and publishes ticket.pdf_generated for downstream
 * consumers (email, download, etc.).
 */
final class GeneratePdfOnQrGeneratedConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private TicketPdfService $ticketPdfService,
        private TicketArtifactService $ticketArtifactService,
        private OutboxService $outboxService,
        private TransactionRunner $transactionRunner,
    ) {}

    public function consumerKey(): string
    {
        return 'tickets.generate_pdf_on_qr_generated';
    }

    protected function eventType(): string
    {
        return 'ticket.qr_generated';
    }

    public function handle(OutboxEvent $event): void
    {
        $payload = $this->innerPayload($event);

        $ticketId = (int) ($payload['ticket_id'] ?? 0);

        if ($ticketId === 0) {
            throw new InvalidArgumentException('ticket.qr_generated outbox payload is missing ticket_id.');
        }

        $this->ticketPdfService->ensurePdf($ticketId);

        $ticket = Ticket::query()->whereKey($ticketId)->firstOrFail();
        $artifact = $this->ticketArtifactService->findLatestReady($ticket, TicketArtifactType::Pdf);

        if ($artifact === null) {
            throw new RuntimeException('PDF artifact must be ready before publishing ticket.pdf_generated.');
        }

        $this->transactionRunner->run(function () use ($ticket, $artifact): void {
            if ($this->pdfGeneratedEventAlreadyRecorded($ticket)) {
                return;
            }

            $this->outboxService->record(
                eventType: 'ticket.pdf_generated',
                aggregate: $ticket,
                payload: [
                    'ticket_id' => $ticket->id,
                    'order_id' => $ticket->order_id,
                    'event_id' => $ticket->event_id,
                    'pdf_path' => $artifact->path,
                    'pdf_version' => $artifact->version,
                ],
            );
        });
    }

    private function pdfGeneratedEventAlreadyRecorded(Ticket $ticket): bool
    {
        return OutboxEvent::query()
            ->where('event_type', 'ticket.pdf_generated')
            ->where('aggregate_type', Ticket::class)
            ->where('aggregate_id', $ticket->id)
            ->exists();
    }
}
