<?php

namespace App\Services\Orders;

use App\Models\Event;
use App\Models\TicketSerialCounter;
use Illuminate\Support\Str;

class TicketSerialService
{
    /**
     * Allocate the next unique serial for an event. Must run inside TransactionRunner::run().
     */
    public function nextSerial(Event $event): string
    {
        $counter = TicketSerialCounter::query()
            ->where('venue_id', $event->venue_id)
            ->where('event_id', $event->id)
            ->lockForUpdate()
            ->first();

        if ($counter === null) {
            $counter = TicketSerialCounter::query()->create([
                'venue_id' => $event->venue_id,
                'event_id' => $event->id,
                'last_serial' => 0,
            ]);

            $counter = TicketSerialCounter::query()
                ->whereKey($counter->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $nextSerial = $counter->last_serial + 1;

        $counter->update(['last_serial' => $nextSerial]);

        return Str::padLeft((string) $nextSerial, 6, '0');
    }
}
