<?php

namespace Database\Factories;

use App\Enums\EventDomain\SeatingUnitStatus;
use App\Models\VenueTable;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VenueTable>
 */
class VenueTableFactory extends Factory
{
    protected $model = VenueTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'table_number' => (string) fake()->unique()->numberBetween(1, 999),
            'capacity' => fake()->numberBetween(2, 12),
            'min_spend' => fake()->optional()->randomFloat(2, 100, 1000),
            'status' => SeatingUnitStatus::Available,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (VenueTable $venueTable): void {
            if ($venueTable->zone_id !== null) {
                $zone = Zone::query()->find($venueTable->zone_id);
                if ($zone !== null) {
                    $venueTable->venue_id = $zone->venue_id;
                    $venueTable->event_id = $zone->event_id;
                }
            }
        });
    }

    public function forZone(Zone $zone): static
    {
        return $this->state(fn (array $attributes) => [
            'zone_id' => $zone->id,
            'event_id' => $zone->event_id,
            'venue_id' => $zone->venue_id,
        ]);
    }
}
