<?php

namespace App\Services\Commerce\Data;

use App\Enums\CommerceDomain\DiscountType;
use App\Models\User;

readonly class CreatePromoCodeData
{
    public function __construct(
        public string $code,
        public DiscountType $discountType,
        public string $discountValue,
        public ?string $minOrderAmount,
        public ?int $maxUses,
        public ?\DateTimeInterface $startsAt,
        public ?\DateTimeInterface $expiresAt,
        public bool $isActive,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
