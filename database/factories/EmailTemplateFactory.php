<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'slug' => fake()->unique()->slug(2),
            'subject' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'variables' => ['customer_name', 'order_number'],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }
}
