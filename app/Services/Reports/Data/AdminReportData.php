<?php

namespace App\Services\Reports\Data;

readonly class AdminReportData
{
    /**
     * @param  array<string, mixed>  $platform
     * @param  array<string, mixed>  $commissions
     * @param  list<array<string, mixed>>  $topVenues
     * @param  list<array<string, mixed>>  $topEvents
     * @param  list<array<string, mixed>>  $paymentMethods
     * @param  array<string, mixed>  $refunds
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public array $platform,
        public array $commissions,
        public array $topVenues,
        public array $topEvents,
        public array $paymentMethods,
        public array $refunds,
        public array $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'commissions' => $this->commissions,
            'top_venues' => $this->topVenues,
            'top_events' => $this->topEvents,
            'payment_methods' => $this->paymentMethods,
            'refunds' => $this->refunds,
            'meta' => $this->meta,
        ];
    }
}
