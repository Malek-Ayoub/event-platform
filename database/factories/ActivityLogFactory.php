<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'actor_user_id' => User::factory(),
            'entity_type' => Event::class,
            'entity_id' => Event::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => ['status' => 'draft'],
            'new_values' => ['status' => 'published'],
            'changed_fields' => ['status'],
            'ip_address' => fake()->ipv4(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ActivityLog $log): void {
            if ($log->entity_id === null) {
                return;
            }

            $entity = Event::query()->find($log->entity_id);
            if ($entity !== null) {
                $log->venue_id = $entity->venue_id;
            }
        });
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }

    public function forActor(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'actor_user_id' => $user->id,
        ]);
    }

    public function forEntity(object $entity): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entity::class,
            'entity_id' => $entity->getKey(),
        ]);
    }
}
