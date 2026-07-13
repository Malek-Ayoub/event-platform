<?php

namespace App\Services\Tickets;

use App\Enums\Tickets\TicketArtifactType;
use App\Mail\TicketIssuedMail;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use App\Services\OutboxService;
use App\Services\Tickets\Artifacts\TicketArtifactService;
use App\Services\Tickets\Data\TicketEmailDeliveryResult;
use App\Services\TransactionRunner;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;

/**
 * Delivers ticket emails with immutable snapshot content and PDF attachments (Phase 8.3.3c).
 */
class TicketEmailService
{
    public function __construct(
        private TicketArtifactService $ticketArtifactService,
        private OutboxService $outboxService,
        private TransactionRunner $transactionRunner,
    ) {}

    public function send(int $ticketId): TicketEmailDeliveryResult
    {
        if (! (bool) config('notifications.ticket_email.enabled', true)) {
            return new TicketEmailDeliveryResult(wasSent: false);
        }

        $ticket = Ticket::query()
            ->with('snapshot')
            ->whereKey($ticketId)
            ->firstOrFail();

        if ($ticket->snapshot === null) {
            throw new InvalidArgumentException('Ticket snapshot is required before email delivery.');
        }

        $pdfArtifact = $this->ticketArtifactService->findLatestReady($ticket, TicketArtifactType::Pdf);

        if ($pdfArtifact === null) {
            throw new RuntimeException('Ready PDF artifact is required before email delivery.');
        }

        $recipient = (string) data_get($ticket->snapshot->payload, 'holder.email', '');

        if (trim($recipient) === '') {
            throw new InvalidArgumentException('Ticket snapshot holder email is required before email delivery.');
        }

        $pdfAttachment = $this->buildPdfAttachment($ticket, $pdfArtifact);
        $mailable = new TicketIssuedMail($ticket->snapshot, $pdfAttachment);

        if ((bool) config('notifications.ticket_email.queue', false)) {
            Mail::to($recipient)->queue($mailable);
        } else {
            Mail::to($recipient)->send($mailable);
        }

        $this->publishEmailSentEvent($ticket, $pdfArtifact, $recipient);

        return new TicketEmailDeliveryResult(wasSent: true);
    }

    private function buildPdfAttachment(Ticket $ticket, TicketArtifact $pdfArtifact): Attachment
    {
        $ticketNumber = (string) data_get($ticket->snapshot?->payload, 'ticket.number', 'ticket');

        return Attachment::fromStorageDisk($pdfArtifact->disk, $pdfArtifact->path)
            ->as("ticket-{$ticketNumber}.pdf")
            ->withMime('application/pdf');
    }

    private function publishEmailSentEvent(Ticket $ticket, TicketArtifact $pdfArtifact, string $recipient): void
    {
        $this->transactionRunner->run(function () use ($ticket, $pdfArtifact, $recipient): void {
            if ($this->emailSentEventAlreadyRecorded($ticket)) {
                return;
            }

            $this->outboxService->record(
                eventType: 'ticket.email_sent',
                aggregate: $ticket,
                payload: [
                    'ticket_id' => $ticket->id,
                    'artifact_version' => $pdfArtifact->version,
                    'recipient' => $recipient,
                    'sent_at' => now()->toIso8601String(),
                ],
            );
        });
    }

    private function emailSentEventAlreadyRecorded(Ticket $ticket): bool
    {
        return OutboxEvent::query()
            ->where('event_type', 'ticket.email_sent')
            ->where('aggregate_type', Ticket::class)
            ->where('aggregate_id', $ticket->id)
            ->exists();
    }
}
