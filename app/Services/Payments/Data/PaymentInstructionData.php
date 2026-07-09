<?php

namespace App\Services\Payments\Data;

use App\DTOs\BaseDTO;
use Illuminate\Support\Carbon;

/**
 * `PaymentInstructionService::createInstructions()` output (Batch 7.6 —
 * IMPLEMENTATION_ROADMAP.md §7.9.5/§7.9.8). No Gateway involvement — pure
 * projection of the newly created `awaiting_transfer` `PaymentTransaction`.
 */
readonly class PaymentInstructionData extends BaseDTO
{
    public function __construct(
        public int $paymentId,
        public string $provider,
        public string $merchantAccount,
        public string $amount,
        public string $currency,
        public Carbon $expiresAt,
        public string $instructions,
    ) {}

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'provider' => $this->provider,
            'merchant_account' => $this->merchantAccount,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'instructions' => $this->instructions,
        ];
    }
}
