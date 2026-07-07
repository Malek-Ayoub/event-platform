<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Base event category templates stored in platform settings until a venue is provisioned.
     *
     * @return list<array{name: string, slug: string, description: string}>
     */
    public static function templates(): array
    {
        return [
            ['name' => 'Concerts', 'slug' => 'concerts', 'description' => 'Live music and concert events.'],
            ['name' => 'Festivals', 'slug' => 'festivals', 'description' => 'Multi-day and outdoor festival events.'],
            ['name' => 'Nightlife', 'slug' => 'nightlife', 'description' => 'Club nights and DJ events.'],
            ['name' => 'Sports', 'slug' => 'sports', 'description' => 'Sporting events and tournaments.'],
            ['name' => 'Conferences', 'slug' => 'conferences', 'description' => 'Professional and business conferences.'],
            ['name' => 'Workshops', 'slug' => 'workshops', 'description' => 'Training sessions and workshops.'],
        ];
    }

    public function run(): void
    {
        // Category rows are venue-scoped. Base templates are seeded via PlatformSettingSeeder.
    }
}
