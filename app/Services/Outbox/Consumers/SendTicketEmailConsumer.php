<?php

namespace App\Services\Outbox\Consumers;

use App\Models\OutboxEvent;
use App\Services\Outbox\AbstractOutboxConsumer;
use App\Services\Tickets\TicketEmailService;
use InvalidArgumentException;

/**
 * Sends ticket delivery emails when a PDF artifact is ready (Phase 8.3.3c).
 */
final class SendTicketEmailConsumer extends AbstractOutboxConsumer
{
    public function __construct(
        private TicketEmailService $ticketEmailService,
    ) {}

    public function consumerKey(): string
    {
        return 'notification.email.ticket';
    }

    protected function eventType(): string
    {
        return 'ticket.pdf_generated';
    }

    public function handle(OutboxEvent $event): void
    {
        $payload = $this->innerPayload($event);

        $ticketId = (int) ($payload['ticket_id'] ?? 0);

        if ($ticketId === 0) {
            throw new InvalidArgumentException('ticket.pdf_generated outbox payload is missing ticket_id.');
        }

        $this->ticketEmailService->send($ticketId);
    }
}
