<?php

namespace App\DTOs\Commerce;

use App\DTOs\BaseDTO;
use App\Enums\CommerceDomain\DiscountType;

readonly class UpdatePromoCodeDTO extends BaseDTO
{
    public function __construct(
        public ?string $code,
        public ?DiscountType $discountType,
        public ?string $discountValue,
        public ?string $minOrderAmount,
        public ?int $maxUses,
        public ?string $startsAt,
        public ?string $expiresAt,
        public ?bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: isset($data['code']) ? (string) $data['code'] : null,
            discountType: isset($data['discount_type']) ? DiscountType::from((string) $data['discount_type']) : null,
            discountValue: isset($data['discount_value']) ? (string) $data['discount_value'] : null,
            minOrderAmount: array_key_exists('min_order_amount', $data) ? ($data['min_order_amount'] !== null ? (string) $data['min_order_amount'] : null) : null,
            maxUses: array_key_exists('max_uses', $data) ? ($data['max_uses'] !== null ? (int) $data['max_uses'] : null) : null,
            startsAt: array_key_exists('starts_at', $data) ? ($data['starts_at'] !== null ? (string) $data['starts_at'] : null) : null,
            expiresAt: array_key_exists('expires_at', $data) ? ($data['expires_at'] !== null ? (string) $data['expires_at'] : null) : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'code' => $this->code,
            'discount_type' => $this->discountType?->value,
            'discount_value' => $this->discountValue,
            'min_order_amount' => $this->minOrderAmount,
            'max_uses' => $this->maxUses,
            'starts_at' => $this->startsAt,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
        ], fn ($value) => $value !== null);
    }
}
