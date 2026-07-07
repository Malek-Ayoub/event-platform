<?php

namespace Database\Factories;

use App\Enums\CommerceDomain\DiscountType;
use App\Models\Coupon;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'code' => Str::upper(fake()->unique()->bothify('CPN-####')),
            'discount_type' => DiscountType::Percent,
            'discount_value' => fake()->randomFloat(2, 5, 25),
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
