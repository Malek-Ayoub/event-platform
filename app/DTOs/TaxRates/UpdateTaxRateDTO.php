<?php

namespace App\DTOs\TaxRates;

use App\DTOs\BaseDTO;

readonly class UpdateTaxRateDTO extends BaseDTO
{
    public function __construct(
        public int $version,
        public ?string $name,
        public ?string $rate,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (int) $data['version'],
            name: isset($data['name']) ? (string) $data['name'] : null,
            rate: isset($data['rate']) ? (string) $data['rate'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'name' => $this->name,
            'rate' => $this->rate,
            'is_active' => $this->isActive,
        ], fn ($value) => $value !== null);
    }
}
