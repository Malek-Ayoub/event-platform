<?php

namespace App\Services\Tickets\CheckIn;

use App\Enums\OrdersDomain\TicketStatus;
use App\Exceptions\Tickets\TicketCheckInRejectedException;
use App\Exceptions\Tickets\TicketNotFoundException;
use App\Models\Ticket;

/**
 * Validates ticket eligibility for check-in without mutating state (Phase 8.4).
 */
final class TicketValidationService
{
    public function findTicketForQrToken(string $qrToken): Ticket
    {
        $ticket = Ticket::query()
            ->with(['event', 'snapshot'])
            ->where('qr_token', $qrToken)
            ->first();

        if ($ticket === null) {
            throw TicketNotFoundException::forQrToken($qrToken);
        }

        return $ticket;
    }

    public function assertEligibleForCheckIn(Ticket $ticket): void
    {
        if ($ticket->status !== TicketStatus::Issued) {
            throw TicketCheckInRejectedException::forStatus($ticket->status);
        }

        $event = $ticket->event;

        if ($event !== null && $event->end_datetime !== null && $event->end_datetime->isPast()) {
            throw TicketCheckInRejectedException::eventEnded();
        }
    }
}
