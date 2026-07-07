<?php

namespace Database\Factories;

use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['shamcash', 'stripe']),
            'provider_event_id' => fake()->unique()->uuid(),
            'payload' => json_encode(['event' => 'payment.completed'], JSON_THROW_ON_ERROR),
            'signature' => fake()->sha256(),
            'status' => WebhookLogStatus::Received,
            'error_message' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookLogStatus::Failed,
            'error_message' => fake()->sentence(),
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => WebhookLogStatus::Processed,
        ]);
    }
}
