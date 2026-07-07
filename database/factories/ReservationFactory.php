<?php

namespace Database\Factories;

use App\Enums\EventDomain\ReservationStatus;
use App\Models\Reservation;
use App\Models\TableSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_seat_id' => TableSeat::factory(),
            'order_id' => null,
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'status' => ReservationStatus::Hold,
            'held_until' => now()->addMinutes(15),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Reservation $reservation): void {
            if ($reservation->table_seat_id !== null) {
                $tableSeat = TableSeat::query()->find($reservation->table_seat_id);
                if ($tableSeat !== null) {
                    $reservation->venue_id = $tableSeat->venue_id;
                }
            }
        });
    }

    public function forTableSeat(TableSeat $tableSeat): static
    {
        return $this->state(fn (array $attributes) => [
            'table_seat_id' => $tableSeat->id,
            'venue_id' => $tableSeat->venue_id,
        ]);
    }
}
