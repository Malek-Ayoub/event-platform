<?php

namespace App\DTOs\TaxRates;

use App\DTOs\BaseDTO;

readonly class CreateTaxRateDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public string $rate,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            rate: (string) $data['rate'],
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'rate' => $this->rate,
            'is_active' => $this->isActive,
        ];
    }
}
