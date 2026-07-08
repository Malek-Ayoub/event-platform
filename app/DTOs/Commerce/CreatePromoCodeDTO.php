<?php

namespace App\DTOs\Commerce;

use App\DTOs\BaseDTO;
use App\Enums\CommerceDomain\DiscountType;

readonly class CreatePromoCodeDTO extends BaseDTO
{
    public function __construct(
        public string $code,
        public DiscountType $discountType,
        public string $discountValue,
        public ?string $minOrderAmount,
        public ?int $maxUses,
        public ?string $startsAt,
        public ?string $expiresAt,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            discountType: DiscountType::from((string) $data['discount_type']),
            discountValue: (string) $data['discount_value'],
            minOrderAmount: isset($data['min_order_amount']) ? (string) $data['min_order_amount'] : null,
            maxUses: isset($data['max_uses']) ? (int) $data['max_uses'] : null,
            startsAt: isset($data['starts_at']) ? (string) $data['starts_at'] : null,
            expiresAt: isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'discount_type' => $this->discountType->value,
            'discount_value' => $this->discountValue,
            'min_order_amount' => $this->minOrderAmount,
            'max_uses' => $this->maxUses,
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
        ];
    }
}
