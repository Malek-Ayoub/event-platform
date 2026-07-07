<?php

namespace Database\Factories;

use App\Enums\EventDomain\SeatingUnitStatus;
use App\Models\TableSeat;
use App\Models\VenueTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSeat>
 */
class TableSeatFactory extends Factory
{
    protected $model = TableSeat::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_table_id' => VenueTable::factory(),
            'seat_number' => (string) fake()->numberBetween(1, 20),
            'status' => SeatingUnitStatus::Available,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TableSeat $tableSeat): void {
            if ($tableSeat->venue_table_id !== null) {
                $venueTable = VenueTable::query()->find($tableSeat->venue_table_id);
                if ($venueTable !== null) {
                    $tableSeat->venue_id = $venueTable->venue_id;
                }
            }
        });
    }

    public function forVenueTable(VenueTable $venueTable): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_table_id' => $venueTable->id,
            'venue_id' => $venueTable->venue_id,
        ]);
    }
}
