<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $settings = [
            'support_email' => 'support@event-platform.test',
            'default_currency' => 'USD',
            'event_category_templates' => CategorySeeder::templates(),
        ];

        $existing = DB::table('platform_settings')->first();

        if ($existing === null) {
            DB::table('platform_settings')->insert([
                'commission_rate' => 1.00,
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $currentSettings = json_decode((string) $existing->settings, true);
        $currentSettings = is_array($currentSettings) ? $currentSettings : [];

        DB::table('platform_settings')
            ->where('id', $existing->id)
            ->update([
                'settings' => json_encode(array_merge($currentSettings, $settings), JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]);
    }
}
