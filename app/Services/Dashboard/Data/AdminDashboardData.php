<?php

namespace App\Services\Dashboard\Data;

readonly class AdminDashboardData
{
    /**
     * @param  array<string, string|int>  $kpis
     * @param  array<string, string|int>  $today
     * @param  list<array<string, mixed>>  $topVenues
     * @param  list<array<string, mixed>>  $topEvents
     * @param  list<array<string, mixed>>  $latestOrders
     * @param  list<array<string, mixed>>  $latestPayments
     * @param  list<array<string, mixed>>  $latestCheckIns
     * @param  list<array<string, mixed>>  $alerts
     * @param  array<string, string|null>  $meta
     */
    public function __construct(
        public array $kpis,
        public array $today,
        public array $topVenues,
        public array $topEvents,
        public array $latestOrders,
        public array $latestPayments,
        public array $latestCheckIns,
        public array $alerts,
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
            'top_venues' => $this->topVenues,
            'top_events' => $this->topEvents,
            'latest_orders' => $this->latestOrders,
            'latest_payments' => $this->latestPayments,
            'latest_check_ins' => $this->latestCheckIns,
            'alerts' => $this->alerts,
            'meta' => $this->meta,
        ];
    }
}
