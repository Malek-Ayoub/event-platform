<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSetting>
 */
class PlatformSettingFactory extends Factory
{
    protected $model = PlatformSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commission_rate' => 1.00,
            'settings' => [
                'support_email' => fake()->companyEmail(),
                'default_currency' => 'USD',
            ],
            'version' => 1,
        ];
    }
}
