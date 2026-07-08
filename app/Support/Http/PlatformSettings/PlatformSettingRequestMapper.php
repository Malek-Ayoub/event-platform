<?php

namespace App\Support\Http\PlatformSettings;

use App\DTOs\PlatformSettings\UpdatePlatformSettingDTO;
use App\Models\User;
use App\Services\PlatformSettings\Data\UpdatePlatformSettingData;

class PlatformSettingRequestMapper
{
    public static function toUpdatePlatformSettingData(UpdatePlatformSettingDTO $dto, ?User $actor, ?string $ipAddress): UpdatePlatformSettingData
    {
        return new UpdatePlatformSettingData(
            expectedVersion: $dto->version,
            commissionRate: $dto->commissionRate,
            settings: $dto->settings,
            actor: $actor,
            ipAddress: $ipAddress,
        );
    }
}
