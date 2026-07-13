<?php

namespace Database\Factories;

use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\Orders\QrTokenGenerator;
use App\Services\Orders\TicketNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

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
            'serial' => Str::padLeft((string) fake()->unique()->numberBetween(1, 999999), 6, '0'),
            'ticket_number' => 'TST-'.fake()->unique()->numerify('######-######'),
            'qr_token' => app(QrTokenGenerator::class)->generate(),
            'issued_at' => now(),
            'qr_code_path' => null,
            'status' => TicketStatus::Issued,
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

            if ($ticket->qr_code_path === null && $ticket->qr_token !== null) {
                $ticket->qr_code_path = "tickets/qr/{$ticket->qr_token}.png";
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

    public function withGeneratedIdentity(Event $event): static
    {
        return $this->state(function () use ($event): array {
            $ticketNumber = app(TicketNumberGenerator::class)->nextForEvent($event);
            $qrToken = app(QrTokenGenerator::class)->generate();

            return [
                'ticket_number' => $ticketNumber,
                'qr_token' => $qrToken,
                'issued_at' => now(),
                'qr_code_path' => "tickets/qr/{$qrToken}.png",
                'status' => TicketStatus::Issued,
            ];
        });
    }

    public function checkedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::CheckedIn,
            'checked_in_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Refunded,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Cancelled,
        ]);
    }

    public function invalidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TicketStatus::Invalidated,
        ]);
    }
}
