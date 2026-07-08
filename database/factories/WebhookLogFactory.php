<?php

namespace Database\Factories;

use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Models\WebhookLog;
use App\Support\Webhooks\WebhookCorrelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookLog>
 */
class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        $provider = fake()->randomElement(['shamcash', 'syriatel_cash']);
        $providerEventId = fake()->unique()->uuid();

        return [
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
            'correlation_id' => WebhookCorrelation::id($provider, $providerEventId),
            'payload' => json_encode(['event_type' => 'payment.completed']),
            'signature' => fake()->sha256(),
            'status' => WebhookLogStatus::Received,
            'error_message' => null,
            'processed_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookLogStatus::Verified,
        ]);
    }

    public function failedSignature(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookLogStatus::FailedSignature,
            'error_message' => 'Invalid signature',
        ]);
    }

    public function replayed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookLogStatus::Replayed,
            'error_message' => 'Duplicate webhook delivery',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookLogStatus::Failed,
            'error_message' => 'Processing failed',
        ]);
    }

    public function processed(): static
    {
        return $this->state(fn (): array => [
            'status' => WebhookLogStatus::Processed,
            'processed_at' => now(),
        ]);
    }
}
