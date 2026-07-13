<?php

namespace Database\Factories;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Models\Event;
use App\Models\Order;
use App\Models\SettlementEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SettlementEntry>
 */
class SettlementEntryFactory extends Factory
{
    protected $model = SettlementEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'order_id' => Order::factory(),
            'payment_transaction_id' => null,
            'type' => SettlementEntryType::CommissionDue,
            'direction' => SettlementEntryDirection::Credit,
            'amount' => fake()->randomFloat(2, 1, 50),
            'currency' => 'USD',
            'reference_type' => 'commission',
            'reference_id' => fake()->unique()->numberBetween(1, 999999),
            'balance_after' => '0.00',
            'correlation_id' => 'commission:'.fake()->unique()->numberBetween(1, 999999),
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SettlementEntry $entry): void {
            if ($entry->order_id !== null) {
                $order = Order::query()->find($entry->order_id);
                if ($order !== null) {
                    $entry->venue_id = $order->venue_id;
                    $entry->event_id = $order->event_id;
                }
            }
        });
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'venue_id' => $order->venue_id,
            'event_id' => $order->event_id,
        ]);
    }
}
