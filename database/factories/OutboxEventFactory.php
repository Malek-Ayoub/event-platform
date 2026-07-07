<?php

namespace Database\Factories;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutboxEvent>
 */
class OutboxEventFactory extends Factory
{
    protected $model = OutboxEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'event_type' => fake()->randomElement(['order.paid', 'refund.processed', 'ticket.checked_in']),
            'aggregate_type' => Order::class,
            'aggregate_id' => Order::factory(),
            'payload' => ['order_id' => fake()->numberBetween(1, 1000)],
            'status' => OutboxEventStatus::Pending,
            'attempts' => 0,
            'processed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (OutboxEvent $event): void {
            if ($event->aggregate_id === null) {
                return;
            }

            $aggregate = Order::query()->find($event->aggregate_id);
            if ($aggregate !== null) {
                $event->venue_id = $aggregate->venue_id;
            }
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OutboxEventStatus::Failed,
            'attempts' => 3,
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OutboxEventStatus::Sent,
            'processed_at' => now(),
        ]);
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }

    public function forAggregate(object $aggregate): static
    {
        return $this->state(fn (array $attributes) => [
            'aggregate_type' => $aggregate::class,
            'aggregate_id' => $aggregate->getKey(),
        ]);
    }
}
