<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketSnapshot>
 */
class TicketSnapshotFactory extends Factory
{
    protected $model = TicketSnapshot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'payload' => [
                'event' => ['name' => fake()->words(3, true)],
                'venue' => ['name' => fake()->company()],
                'holder' => ['name' => fake()->name()],
            ],
        ];
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
        ]);
    }
}
