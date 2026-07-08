<?php

namespace App\DTOs\Commerce;

use App\DTOs\BaseDTO;

readonly class UpdateProductVariantDTO extends BaseDTO
{
    public function __construct(
        public ?string $name,
        public ?string $sku,
        public ?string $priceOverride,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            sku: array_key_exists('sku', $data) ? ($data['sku'] !== null ? (string) $data['sku'] : null) : null,
            priceOverride: array_key_exists('price_override', $data) ? ($data['price_override'] !== null ? (string) $data['price_override'] : null) : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'sku' => $this->sku,
            'price_override' => $this->priceOverride,
            'is_active' => $this->isActive,
        ], fn ($value) => $value !== null);
    }
}
