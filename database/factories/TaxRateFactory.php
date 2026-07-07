<?php

namespace Database\Factories;

use App\Models\TaxRate;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRate>
 */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => fake()->randomElement(['VAT', 'Sales Tax', 'Service Tax']),
            'rate' => fake()->randomFloat(4, 0.05, 0.20),
            'is_active' => true,
            'version' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
