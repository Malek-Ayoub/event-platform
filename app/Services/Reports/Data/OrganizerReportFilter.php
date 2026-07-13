<?php

namespace App\Services\Reports\Data;

use App\Services\Settlements\Data\SettlementDateRange;

readonly class OrganizerReportFilter
{
    public function __construct(
        public int $venueId,
        public SettlementDateRange $range,
        public ?int $eventId = null,
    ) {}
}
