<?php

namespace App\Http\Requests\PlatformSettings;

use App\Http\Requests\Api\BaseApiRequest;
use App\Models\PlatformSetting;

class ShowPlatformSettingRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PlatformSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
