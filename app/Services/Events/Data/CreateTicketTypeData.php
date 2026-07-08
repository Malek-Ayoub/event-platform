<?php

namespace App\Services\Events\Data;

use App\Models\User;

readonly class CreateTicketTypeData
{
    /**
     * @param  list<string>|null  $benefits
     */
    public function __construct(
        public int $eventId,
        public string $name,
        public string $price,
        public int $quantity,
        public ?\DateTimeInterface $saleStart,
        public ?\DateTimeInterface $saleEnd,
        public ?array $benefits,
        public ?string $color,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
