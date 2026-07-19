<?php

namespace App\Services\Venues\Data;

use App\Models\User;

readonly class CreateVenueData
{
    public function __construct(
        public string $name,
        public string $subdomain,
        public string $ownerName,
        public string $ownerEmail,
        public string $ownerPassword,
        public User $actor,
        public ?string $ipAddress = null,
    ) {}
}
