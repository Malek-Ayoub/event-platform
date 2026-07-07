<?php

namespace Database\Factories;

use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 500);

        return [
            'event_id' => Event::factory(),
            'customer_user_id' => null,
            'order_number' => 'ORD-'.Str::upper(fake()->unique()->bothify('??######')),
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $subtotal,
            'commission_amount' => 0,
            'coupon_id' => null,
            'promo_code_id' => null,
            'payment_method' => fake()->optional()->randomElement(['card', 'cash', 'shamcash']),
            'payment_reference' => fake()->optional()->uuid(),
            'status' => OrderStatus::Pending,
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->optional()->phoneNumber(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Order $order): void {
            if ($order->event_id !== null) {
                $event = Event::query()->find($order->event_id);
                if ($event !== null) {
                    $order->venue_id = $event->venue_id;
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

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
        ]);
    }
}
