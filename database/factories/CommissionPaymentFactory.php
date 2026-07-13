<?php

namespace Database\Factories;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Models\CommissionPayment;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionPayment>
 */
class CommissionPaymentFactory extends Factory
{
    protected $model = CommissionPayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'payment_account_id' => null,
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'payment_method' => CommissionPaymentMethod::Shamcash,
            'reference_number' => 'CP-'.fake()->unique()->numerify('######'),
            'received_at' => now(),
            'received_by_user_id' => User::factory(),
            'notes' => null,
            'metadata' => null,
        ];
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }
}
