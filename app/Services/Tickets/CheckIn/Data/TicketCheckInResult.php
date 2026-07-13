<?php

namespace App\Services\Tickets\CheckIn\Data;

use App\Enums\OrdersDomain\TicketStatus;

final class TicketCheckInResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $ticketNumber,
        public readonly string $holderName,
        public readonly string $eventName,
        public readonly TicketStatus $status,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'ticket_number' => $this->ticketNumber,
            'holder_name' => $this->holderName,
            'event_name' => $this->eventName,
            'status' => $this->status->value,
        ];
    }
}
