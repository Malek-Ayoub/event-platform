<?php

namespace App\Services\Reports\Data;

use App\Services\Settlements\Data\SettlementDateRange;

readonly class AdminReportFilter
{
    public function __construct(
        public SettlementDateRange $range,
        public int $limit = 10,
    ) {}
}
