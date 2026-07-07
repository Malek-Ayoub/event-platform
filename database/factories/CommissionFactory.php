<?php

namespace Database\Factories;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Models\Commission;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 1, 50),
            'rate' => fake()->randomFloat(2, 1, 10),
            'status' => CommissionStatus::Pending,
            'payout_reference' => null,
            'paid_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Commission $commission): void {
            if ($commission->order_id !== null) {
                $order = Order::query()->find($commission->order_id);
                if ($order !== null) {
                    $commission->venue_id = $order->venue_id;
                }
            }
        });
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
            'venue_id' => $order->venue_id,
        ]);
    }
}
