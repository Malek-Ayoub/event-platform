<?php

namespace App\Services\Tickets\CheckIn;

use App\Enums\OrdersDomain\TicketStatus;
use App\Exceptions\Tickets\TicketNotFoundException;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Services\OutboxService;
use App\Services\Tickets\CheckIn\Data\CheckInTicketData;
use App\Services\Tickets\CheckIn\Data\TicketCheckInResult;
use App\Services\TransactionRunner;
use Illuminate\Support\Carbon;

/**
 * Performs ticket check-in with row locking and immutable audit history (Phase 8.4).
 *
 * `ticket_check_ins` is the source of truth for admission history.
 * `tickets.checked_in_at` / `checked_in_by` are denormalized caches of the
 * latest check-in only (fast lookups; supports future re-entry without redesign).
 */
final class TicketCheckInService
{
    public function __construct(
        private TicketValidationService $validationService,
        private OutboxService $outboxService,
        private TransactionRunner $transactionRunner,
    ) {}

    public function checkIn(CheckInTicketData $data): TicketCheckInResult
    {
        return $this->transactionRunner->run(function () use ($data): TicketCheckInResult {
            $ticket = Ticket::query()
                ->with(['snapshot'])
                ->where('qr_token', $data->qrToken)
                ->lockForUpdate()
                ->first();

            if ($ticket === null) {
                throw TicketNotFoundException::forQrToken($data->qrToken);
            }

            $this->validationService->assertEligibleForCheckIn($ticket);

            $checkedInAt = now();

            TicketCheckIn::query()->create([
                'ticket_id' => $ticket->id,
                'checked_in_at' => $checkedInAt,
                'checked_in_by_user_id' => $data->checkedInByUserId,
                'gate_id' => $data->gateId,
                'device_id' => $data->deviceId,
                'notes' => $data->notes,
                'created_at' => $checkedInAt,
            ]);

            $this->syncLatestCheckInCache($ticket, $checkedInAt, $data->checkedInByUserId);

            $this->outboxService->record(
                eventType: 'ticket.checked_in',
                aggregate: $ticket->fresh(),
                payload: [
                    'ticket_id' => $ticket->id,
                    'event_id' => $ticket->event_id,
                    'venue_id' => $ticket->venue_id,
                    'checked_in_at' => $checkedInAt->toIso8601String(),
                    'checked_in_by_user_id' => $data->checkedInByUserId,
                    'gate_id' => $data->gateId,
                    'device_id' => $data->deviceId,
                ],
            );

            return $this->buildResult($ticket->fresh());
        });
    }

    /**
     * Denormalized cache on `tickets` — mirrors the latest row in `ticket_check_ins`.
     */
    private function syncLatestCheckInCache(Ticket $ticket, Carbon $checkedInAt, int $checkedInByUserId): void
    {
        $ticket->update([
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => $checkedInAt,
            'checked_in_by' => $checkedInByUserId,
        ]);
    }

    private function buildResult(Ticket $ticket): TicketCheckInResult
    {
        $payload = $ticket->snapshot?->payload ?? [];

        return new TicketCheckInResult(
            valid: true,
            ticketNumber: (string) data_get($payload, 'ticket.number', $ticket->ticket_number),
            holderName: (string) data_get($payload, 'holder.name', ''),
            eventName: (string) data_get($payload, 'event.name', ''),
            status: TicketStatus::CheckedIn,
        );
    }
}
