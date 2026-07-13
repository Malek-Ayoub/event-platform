<?php

namespace App\Services\Settlements\Data;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use Illuminate\Support\Carbon;

readonly class AppendSettlementEntryData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $venueId,
        public int $eventId,
        public int $orderId,
        public SettlementEntryType $type,
        public SettlementEntryDirection $direction,
        public string $amount,
        public string $currency,
        public string $referenceType,
        public int $referenceId,
        public Carbon $occurredAt,
        public ?int $paymentTransactionId = null,
        public ?string $correlationId = null,
        public ?array $metadata = null,
    ) {}
}
