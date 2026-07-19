<?php

namespace App\Services\Venues\Data;

use App\Models\User;

readonly class UpdateVenueData
{
    public function __construct(
        public User $actor,
        public ?string $name = null,
        public ?string $commissionRate = null,
        public ?string $ipAddress = null,
    ) {}
}
