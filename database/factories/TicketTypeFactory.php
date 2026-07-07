<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    protected $model = TicketType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->words(2, true),
            'price' => fake()->randomFloat(2, 10, 500),
            'quantity' => fake()->numberBetween(50, 500),
            'quantity_sold' => 0,
            'sale_start' => now()->subDay(),
            'sale_end' => now()->addMonth(),
            'benefits' => null,
            'color' => fake()->hexColor(),
            'version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TicketType $ticketType): void {
            if ($ticketType->event_id !== null) {
                $event = Event::query()->find($ticketType->event_id);
                if ($event !== null) {
                    $ticketType->venue_id = $event->venue_id;
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
