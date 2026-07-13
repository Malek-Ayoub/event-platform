<?php

namespace App\Mail;

use App\Models\TicketSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Ticket delivery email rendered exclusively from immutable snapshot data (Phase 8.3.3c).
 */
class TicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TicketSnapshot $snapshot,
        public Attachment $pdfAttachment,
    ) {}

    public function envelope(): Envelope
    {
        $eventName = (string) data_get($this->snapshot->payload, 'event.name', 'Event');

        return new Envelope(
            from: new Address(
                (string) config('notifications.ticket_email.from_email'),
                (string) config('notifications.ticket_email.from_name'),
            ),
            subject: "Your ticket for {$eventName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-issued',
            with: [
                'snapshot' => $this->snapshot->payload,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [$this->pdfAttachment];
    }
}
