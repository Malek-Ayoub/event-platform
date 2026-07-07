<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionAdjustment>
 */
class CommissionAdjustmentFactory extends Factory
{
    protected $model = CommissionAdjustment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commission_id' => Commission::factory(),
            'refund_id' => Refund::factory(),
            'adjustment_amount' => fake()->randomFloat(2, 1, 25),
            'rate_snapshot' => fake()->randomFloat(2, 1, 10),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CommissionAdjustment $adjustment): void {
            if ($adjustment->commission_id !== null) {
                $commission = Commission::query()->find($adjustment->commission_id);
                if ($commission !== null) {
                    $adjustment->venue_id = $commission->venue_id;
                }
            }

            if ($adjustment->refund_id !== null && $adjustment->venue_id === null) {
                $refund = Refund::query()->find($adjustment->refund_id);
                if ($refund !== null) {
                    $adjustment->venue_id = $refund->venue_id;
                }
            }
        });
    }

    public function forCommissionAndRefund(Commission $commission, Refund $refund): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_id' => $commission->id,
            'refund_id' => $refund->id,
            'venue_id' => $commission->venue_id,
        ]);
    }
}
