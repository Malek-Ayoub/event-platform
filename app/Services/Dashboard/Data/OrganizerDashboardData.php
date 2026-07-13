<?php

namespace App\Services\Dashboard\Data;

readonly class OrganizerDashboardData
{
    /**
     * @param  array<string, string|int>  $kpis
     * @param  array<string, string|int>  $today
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $latestOrders
     * @param  list<array<string, mixed>>  $latestCheckIns
     * @param  array<string, string>  $commission
     * @param  array<string, string|null>  $meta
     */
    public function __construct(
        public array $kpis,
        public array $today,
        public array $events,
        public array $latestOrders,
        public array $latestCheckIns,
        public array $commission,
        public array $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kpis' => $this->kpis,
            'today' => $this->today,
            'events' => $this->events,
            'latest_orders' => $this->latestOrders,
            'latest_check_ins' => $this->latestCheckIns,
            'commission' => $this->commission,
            'meta' => $this->meta,
        ];
    }
}
