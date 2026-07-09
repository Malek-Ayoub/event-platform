<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiResource;
use App\Services\Payments\Data\PaymentInstructionData;
use Illuminate\Http\Request;

/** @mixin PaymentInstructionData */
class PaymentInstructionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentInstructionData $instruction */
        $instruction = $this->resource;

        return [
            'payment_id' => $instruction->paymentId,
            'provider' => $instruction->provider,
            'merchant_account' => $instruction->merchantAccount,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'expires_at' => $instruction->expiresAt->toIso8601String(),
            'instructions' => $instruction->instructions,
        ];
    }
}
