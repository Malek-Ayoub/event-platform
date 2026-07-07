<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['order.paid', 'refund.processed', 'ticket.checked_in']),
            'data' => [
                'message' => fake()->sentence(),
            ],
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }
}
