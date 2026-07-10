<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketNumberCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketNumberCounter>
 */
class TicketNumberCounterFactory extends Factory
{
    protected $model = TicketNumberCounter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'last_number' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TicketNumberCounter $counter): void {
            if ($counter->event_id !== null) {
                $event = Event::query()->find($counter->event_id);
                if ($event !== null) {
                    $counter->venue_id = $event->venue_id;
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
