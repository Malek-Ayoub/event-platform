<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketCheckIn>
 */
class TicketCheckInFactory extends Factory
{
    protected $model = TicketCheckIn::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'checked_in_at' => now(),
            'checked_in_by_user_id' => User::factory(),
            'gate_id' => null,
            'device_id' => null,
            'notes' => null,
            'created_at' => now(),
        ];
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
        ]);
    }
}
