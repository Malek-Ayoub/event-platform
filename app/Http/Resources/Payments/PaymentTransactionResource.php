<?php

namespace App\Http\Resources\Payments;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Orders\OrderResource;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

/** @mixin PaymentTransaction */
class PaymentTransactionResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', fn () => new OrderResource($this->order)),
            'provider' => $this->provider,
            'provider_transaction_id' => $this->provider_transaction_id,
            'transaction_number' => $this->transaction_number,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'payload' => $this->when(
                $request->user()?->can('update', $this->resource) ?? false,
                $this->payload,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
