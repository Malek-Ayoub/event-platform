<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventPaymentAccount;
use App\Models\PaymentAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventPaymentAccount>
 */
class EventPaymentAccountFactory extends Factory
{
    protected $model = EventPaymentAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'payment_account_id' => PaymentAccount::factory(),
            'is_default' => true,
            'is_active' => true,
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (array $attributes) => [
            'event_id' => $event->id,
        ]);
    }

    public function forPaymentAccount(PaymentAccount $account): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_account_id' => $account->id,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function notDefault(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
