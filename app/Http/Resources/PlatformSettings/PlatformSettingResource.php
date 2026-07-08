<?php

namespace App\Http\Resources\PlatformSettings;

use App\Http\Resources\ApiResource;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

/** @mixin PlatformSetting */
class PlatformSettingResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commission_rate' => $this->commission_rate,
            'settings' => $this->settings,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
