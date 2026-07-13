<?php

namespace App\Services\Settlements\Data;

use Illuminate\Support\Carbon;

readonly class SettlementDateRange
{
    public function __construct(
        public ?Carbon $from = null,
        public ?Carbon $to = null,
    ) {}

    public function hasFilter(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    public function appliesTo(Carbon $occurredAt): bool
    {
        if ($this->from !== null && $occurredAt->lt($this->from)) {
            return false;
        }

        if ($this->to !== null && $occurredAt->gt($this->to)) {
            return false;
        }

        return true;
    }
}
