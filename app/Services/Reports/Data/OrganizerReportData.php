<?php

namespace App\Services\Reports\Data;

readonly class OrganizerReportData
{
    /**
     * @param  array<string, string|int>  $sales
     * @param  array<string, string>  $revenue
     * @param  array<string, string|int>  $attendance
     * @param  array<string, string>  $commission
     * @param  array<string, string|int|null>  $meta
     */
    public function __construct(
        public array $sales,
        public array $revenue,
        public array $attendance,
        public array $commission,
        public array $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sales' => $this->sales,
            'revenue' => $this->revenue,
            'attendance' => $this->attendance,
            'commission' => $this->commission,
            'meta' => $this->meta,
        ];
    }
}
