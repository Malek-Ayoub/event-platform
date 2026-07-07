<?php

namespace Database\Factories;

use App\Enums\InfrastructureDomain\MediaType;
use App\Models\Event;
use App\Models\Media;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'mediable_type' => Event::class,
            'mediable_id' => Event::factory(),
            'type' => MediaType::Image,
            'url' => fake()->imageUrl(),
            'sort_order' => 0,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Media $media): void {
            if ($media->mediable_id === null) {
                return;
            }

            $mediable = Event::query()->find($media->mediable_id);
            if ($mediable !== null) {
                $media->venue_id = $mediable->venue_id;
            }
        });
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => MediaType::Video,
            'url' => fake()->url(),
        ]);
    }

    public function forMediable(object $mediable): static
    {
        return $this->state(fn (array $attributes) => [
            'mediable_type' => $mediable::class,
            'mediable_id' => $mediable->getKey(),
            'venue_id' => $mediable->getAttribute('venue_id') ?? $attributes['venue_id'],
        ]);
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }
}
