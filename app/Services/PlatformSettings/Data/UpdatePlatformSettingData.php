<?php

namespace App\Services\PlatformSettings\Data;

use App\Models\User;

readonly class UpdatePlatformSettingData
{
    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function __construct(
        public int $expectedVersion,
        public ?string $commissionRate = null,
        public ?array $settings = null,
        public ?User $actor = null,
        public ?string $ipAddress = null,
    ) {}
}
