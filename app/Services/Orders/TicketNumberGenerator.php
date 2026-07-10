<?php

namespace App\Services\Orders;

use App\Models\Event;
use App\Models\TicketNumberCounter;
use Illuminate\Support\Str;

/**
 * Generates human-readable ticket numbers scoped per event (Phase 8.3.2).
 *
 * Format: EV000042-250712-000001
 */
final class TicketNumberGenerator
{
    /**
     * Allocate the next unique ticket number for an event. Must run inside TransactionRunner::run().
     */
    public function nextForEvent(Event $event): string
    {
        $counter = TicketNumberCounter::query()
            ->where('venue_id', $event->venue_id)
            ->where('event_id', $event->id)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            $counter = TicketNumberCounter::query()->create([
                'venue_id' => $event->venue_id,
                'event_id' => $event->id,
                'last_number' => 0,
            ]);

            $counter = TicketNumberCounter::query()
                ->whereKey($counter->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $nextNumber = $counter->last_number + 1;

        $counter->update(['last_number' => $nextNumber]);

        return $this->format($event, $nextNumber);
    }

    private function format(Event $event, int $sequence): string
    {
        $prefix = (string) config('tickets.number.event_prefix', 'EV');
        $eventIdPad = (int) config('tickets.number.event_id_pad', 6);
        $sequencePad = (int) config('tickets.number.sequence_pad', 6);

        $eventPart = $prefix.Str::padLeft((string) $event->id, $eventIdPad, '0');
        $datePart = ($event->start_datetime ?? now())->format('ymd');
        $sequencePart = Str::padLeft((string) $sequence, $sequencePad, '0');

        return "{$eventPart}-{$datePart}-{$sequencePart}";
    }
}
