<?php

namespace Database\Factories;

use App\Enums\Payments\PaymentWalletProvider;
use App\Models\PaymentAccount;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAccount>
 */
class PaymentAccountFactory extends Factory
{
    protected $model = PaymentAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'provider' => PaymentWalletProvider::ShamCash,
            'account_identifier' => '251aw'.fake()->unique()->bothify('****************'),
            'cash_code' => null,
            'currency' => 'USD',
            'display_name' => fake()->company().' Wallet',
        ];
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }

    public function shamcash(string $accountIdentifier = '251awTESTMERCHANT000000000000'): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => PaymentWalletProvider::ShamCash,
            'account_identifier' => $accountIdentifier,
            'cash_code' => null,
            'currency' => 'USD',
        ]);
    }

    public function syriatel(string $gsm = '0933123456', ?string $cashCode = '123456'): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => PaymentWalletProvider::Syriatel,
            'account_identifier' => $gsm,
            'cash_code' => $cashCode,
            'currency' => 'SYP',
        ]);
    }
}
