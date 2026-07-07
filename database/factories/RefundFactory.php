<?php

namespace Database\Factories;

use App\Enums\FinancialDomain\RefundStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'payment_transaction_id' => null,
            'amount' => fake()->randomFloat(2, 10, 200),
            'status' => RefundStatus::Pending,
            'reason' => fake()->optional()->sentence(),
            'provider_refund_id' => fake()->optional()->uuid(),
            'processed_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Refund $refund): void {
            if ($refund->order_id !== null) {
                $order = Order::query()->find($refund->order_id);
                if ($order !== null) {
                    $refund->venue_id = $order->venue_id;
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

    public function forPaymentTransaction(PaymentTransaction $paymentTransaction): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $paymentTransaction->order_id,
            'venue_id' => $paymentTransaction->venue_id,
            'payment_transaction_id' => $paymentTransaction->id,
        ]);
    }

    public function withoutPaymentTransaction(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_transaction_id' => null,
        ]);
    }
}
