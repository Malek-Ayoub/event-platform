<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price' => fake()->randomFloat(2, 25, 250),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OrderItem $item): void {
            if ($item->order_id !== null) {
                $order = Order::query()->find($item->order_id);
                if ($order !== null) {
                    $item->venue_id = $order->venue_id;
                }
            }
        });
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'venue_id' => $order->venue_id,
        ]);
    }

    public function forTicketType(TicketType $ticketType): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_type_id' => $ticketType->id,
        ]);
    }
}
