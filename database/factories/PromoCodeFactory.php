<?php

namespace Database\Factories;

use App\Enums\CommerceDomain\DiscountType;
use App\Models\PromoCode;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    protected $model = PromoCode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'code' => Str::upper(fake()->unique()->bothify('PROMO-####')),
            'discount_type' => DiscountType::Fixed,
            'discount_value' => fake()->randomFloat(2, 10, 50),
            'min_order_amount' => fake()->optional()->randomFloat(2, 50, 200),
            'max_uses' => fake()->optional()->numberBetween(10, 100),
            'used_count' => 0,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
