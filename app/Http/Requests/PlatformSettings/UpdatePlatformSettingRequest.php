<?php

namespace App\Http\Requests\PlatformSettings;

use App\DTOs\BaseDTO;
use App\DTOs\PlatformSettings\UpdatePlatformSettingDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\PlatformSetting;

class UpdatePlatformSettingRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        $setting = PlatformSetting::query()->orderBy('id')->first();

        return $setting !== null && ($this->user()?->can('update', $setting) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdatePlatformSettingDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'commission_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'settings' => ['sometimes', 'array'],
        ];
    }
}
