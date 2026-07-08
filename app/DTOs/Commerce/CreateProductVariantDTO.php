<?php

namespace App\DTOs\Commerce;

use App\DTOs\BaseDTO;

readonly class CreateProductVariantDTO extends BaseDTO
{
    public function __construct(
        public int $productId,
        public string $name,
        public ?string $sku,
        public ?string $priceOverride,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            name: (string) $data['name'],
            sku: isset($data['sku']) ? (string) $data['sku'] : null,
            priceOverride: isset($data['price_override']) ? (string) $data['price_override'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'name' => $this->name,
            'sku' => $this->sku,
            'price_override' => $this->priceOverride,
            'is_active' => $this->isActive,
        ];
    }
}
