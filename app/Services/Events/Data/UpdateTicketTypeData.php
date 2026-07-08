<?php

namespace App\Services\Events\Data;

use App\Models\User;

readonly class UpdateTicketTypeData
{
    /**
     * @param  list<string>|null  $benefits
     */
    public function __construct(
        public int $expectedVersion,
        public ?string $name = null,
        public ?string $price = null,
        public ?int $quantity = null,
        public ?\DateTimeInterface $saleStart = null,
        public ?\DateTimeInterface $saleEnd = null,
        public ?array $benefits = null,
        public ?string $color = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
