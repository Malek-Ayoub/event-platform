<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Zone $zone): void {
            if ($zone->event_id !== null) {
                $event = Event::query()->find($zone->event_id);
                if ($event !== null) {
                    $zone->venue_id = $event->venue_id;
                }
            }
        });
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
            'venue_id' => $event->venue_id,
        ]);
    }
}
