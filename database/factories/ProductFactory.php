<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Product;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'event_id' => null,
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->randomFloat(2, 5, 200),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            if ($product->event_id !== null) {
                $event = Event::query()->find($product->event_id);
                if ($event !== null) {
                    $product->venue_id = $event->venue_id;
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

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
