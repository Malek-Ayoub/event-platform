<?php

namespace App\Http\Resources\Commissions;

use App\Http\Resources\ApiResource;
use App\Models\CommissionPayment;
use Illuminate\Http\Request;

/** @mixin CommissionPayment */
class CommissionPaymentResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venue_id' => $this->venue_id,
            'payment_account_id' => $this->payment_account_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method->value,
            'reference_number' => $this->reference_number,
            'received_at' => $this->received_at?->toIso8601String(),
            'received_by_user_id' => $this->received_by_user_id,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'outstanding_commission' => $this->when(
                isset($this->resource->outstanding_commission),
                $this->resource->outstanding_commission,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
