<?php

namespace Database\Factories;

use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => null,
            'serial' => (string) fake()->unique()->numberBetween(100000, 999999),
            'qr_code_path' => fake()->optional()->filePath(),
            'status' => TicketStatus::Valid,
            'checked_in_at' => null,
            'checked_in_by' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Ticket $ticket): void {
            if ($ticket->order_id === null) {
                return;
            }

            $order = Order::query()->find($ticket->order_id);
            if ($order === null) {
                return;
            }

            $ticket->venue_id = $order->venue_id;
            $ticket->event_id = $order->event_id;

            if ($ticket->ticket_type_id === null) {
                $ticketType = TicketType::factory()->create([
                    'event_id' => $order->event_id,
                    'venue_id' => $order->venue_id,
                ]);
                $ticket->ticket_type_id = $ticketType->id;
            }
        });
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'event_id' => $order->event_id,
            'venue_id' => $order->venue_id,
        ]);
    }

    public function forTicketType(TicketType $ticketType): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_type_id' => $ticketType->id,
            'event_id' => $ticketType->event_id,
            'venue_id' => $ticketType->venue_id,
        ]);
    }
}
