<?php

namespace Database\Factories;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'shamcash',
            'provider_transaction_id' => 'TXN-'.Str::upper(fake()->unique()->bothify('??########')),
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Pending,
            'payload' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PaymentTransaction $transaction): void {
            if ($transaction->order_id !== null) {
                $order = Order::query()->find($transaction->order_id);
                if ($order !== null) {
                    $transaction->venue_id = $order->venue_id;
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

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::Completed,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::Failed,
        ]);
    }

    /**
     * Manual Wallet Transfer states (§7.9 — Batch 7.6).
     */
    public function awaitingTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_transaction_id' => null,
            'transaction_number' => null,
            'status' => PaymentTransactionStatus::AwaitingTransfer,
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function verifying(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_number' => 'TX-'.Str::upper(fake()->unique()->bothify('########')),
            'status' => PaymentTransactionStatus::Verifying,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::Paid,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentTransactionStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);
    }
}
