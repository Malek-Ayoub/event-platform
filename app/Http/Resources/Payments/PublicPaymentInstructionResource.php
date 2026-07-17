<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiResource;
use App\Services\Payments\Data\PaymentInstructionData;
use Illuminate\Http\Request;

/**
 * Limited public projection of payment instructions — no numeric payment ids.
 *
 * @mixin PaymentInstructionData
 */
class PublicPaymentInstructionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PaymentInstructionData $instruction */
        $instruction = $this->resource;

        return [
            'provider' => $instruction->walletProvider ?? $instruction->provider,
            'merchant_account' => $instruction->merchantAccount,
            'amount' => $instruction->amount,
            'currency' => $instruction->currency,
            'expires_at' => $instruction->expiresAt->toIso8601String(),
            'instructions' => $instruction->instructions,
        ];
    }
}
