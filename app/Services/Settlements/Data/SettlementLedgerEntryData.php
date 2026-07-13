<?php

namespace App\Services\Settlements\Data;

readonly class SettlementLedgerEntryData
{
    public function __construct(
        public int $id,
        public string $date,
        public string $type,
        public string $description,
        public string $credit,
        public string $debit,
        public string $balance,
        public ?int $orderId = null,
        public ?int $eventId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'date' => $this->date,
            'type' => $this->type,
            'description' => $this->description,
            'credit' => $this->credit,
            'debit' => $this->debit,
            'balance' => $this->balance,
            'order_id' => $this->orderId,
            'event_id' => $this->eventId,
        ], fn ($value) => $value !== null);
    }
}
