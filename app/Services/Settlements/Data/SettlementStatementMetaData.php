<?php

namespace App\Services\Settlements\Data;

readonly class SettlementStatementMetaData
{
    /**
     * @param  array<string, mixed>  $pagination
     */
    public function __construct(
        public ?string $from,
        public ?string $to,
        public string $currency,
        public string $openingBalance,
        public array $pagination = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'currency' => $this->currency,
            'opening_balance' => $this->openingBalance,
            'pagination' => $this->pagination !== [] ? $this->pagination : null,
        ], fn ($value) => $value !== null);
    }
}
