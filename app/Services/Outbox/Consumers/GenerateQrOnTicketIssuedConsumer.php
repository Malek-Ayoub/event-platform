<?php

namespace App\Services\Outbox\Consumers;

use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Services\Outbox\AbstractOutboxConsumer;
use App\Services\OutboxService;
use App\Services\Tickets\TicketQrService;
use App\Services\TransactionRunner;
use InvalidArgumentException;

/**
 * Generates QR image artifacts when a ticket is issued (Phase 8.3.3a).
 *
 * Listens to the general domain event ticket.issued and publishes ticket.qr_generated
 * when the PNG artifact is available for downstream consumers (PDF, email, etc.).
 */
final class GenerateQrOnTicketIssuedConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private TicketQrService $ticketQrService,
        private OutboxService $outboxService,
        private TransactionRunner $transactionRunner,
    ) {}

    public function consumerKey(): string
    {
        return 'tickets.generate_qr_on_issued';
    }

    protected function eventType(): string
    {
        return 'ticket.issued';
    }

    public function handle(OutboxEvent $event): void
    {
        $payload = $this->innerPayload($event);

        $ticketId = (int) ($payload['ticket_id'] ?? 0);

        if ($ticketId === 0) {
            throw new InvalidArgumentException('ticket.issued outbox payload is missing ticket_id.');
        }

        $result = $this->ticketQrService->ensureQrImage($ticketId);

        $ticket = Ticket::query()->whereKey($ticketId)->firstOrFail();

        $this->transactionRunner->run(function () use ($ticket, $result): void {
            if ($this->qrGeneratedEventAlreadyRecorded($ticket)) {
                return;
            }

            $this->outboxService->record(
                eventType: 'ticket.qr_generated',
                aggregate: $ticket,
                payload: [
                    'ticket_id' => $ticket->id,
                    'order_id' => $ticket->order_id,
                    'event_id' => $ticket->event_id,
                    'qr_code_path' => $result->storagePath,
                ],
            );
        });
    }

    private function qrGeneratedEventAlreadyRecorded(Ticket $ticket): bool
    {
        return OutboxEvent::query()
            ->where('event_type', 'ticket.qr_generated')
            ->where('aggregate_type', Ticket::class)
            ->where('aggregate_id', $ticket->id)
            ->exists();
    }
}
