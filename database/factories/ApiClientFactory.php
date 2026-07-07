<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /**
     * Plain secret used when creating clients in tests.
     */
    public static string $plainSecret = 'test-api-secret';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'name' => fake()->words(2, true),
            'api_key' => 'key_'.Str::random(24),
            'secret' => Hash::make(static::$plainSecret),
            'scopes' => ['events.read'],
            'active' => true,
            'expires_at' => null,
            'last_used_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
