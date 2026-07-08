<?php

namespace App\DTOs\PlatformSettings;

use App\DTOs\BaseDTO;

readonly class UpdatePlatformSettingDTO extends BaseDTO
{
    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function __construct(
        public int $version,
        public ?string $commissionRate,
        public ?array $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (int) $data['version'],
            commissionRate: isset($data['commission_rate']) ? (string) $data['commission_rate'] : null,
            settings: isset($data['settings']) ? $data['settings'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'commission_rate' => $this->commissionRate,
            'settings' => $this->settings,
        ], fn ($value) => $value !== null);
    }
}
