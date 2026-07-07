<?php

namespace Database\Factories;

use App\Enums\EventDomain\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        $start = fake()->dateTimeBetween('+1 week', '+1 month');

        return [
            'venue_id' => Venue::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->paragraph(),
            'banner_url' => fake()->optional()->url(),
            'gallery' => null,
            'video_url' => fake()->optional()->url(),
            'dj_info' => null,
            'start_datetime' => $start,
            'end_datetime' => (clone $start)->modify('+4 hours'),
            'status' => EventStatus::Draft,
            'version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Event $event): void {
            if ($event->category_id !== null) {
                return;
            }

            $category = Category::factory()->create([
                'venue_id' => $event->venue_id,
            ]);
            $event->update(['category_id' => $category->id]);
        });
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
        ]);
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $category->venue_id,
            'category_id' => $category->id,
        ]);
    }

    public function withoutCategory(): static
    {
        return $this->afterCreating(function (Event $event): void {
            $event->update(['category_id' => null]);
        });
    }
}
