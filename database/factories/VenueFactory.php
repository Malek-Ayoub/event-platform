<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'subdomain' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'owner_user_id' => null,
            'theme_config' => null,
            'shamcash_account_id' => null,
            'commission_rate' => 1.00,
            'status' => 'active',
            'version' => 1,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
}
