<?php

namespace App\Http\Resources\Commerce;

use App\Http\Resources\ApiResource;
use App\Models\PromoCode;
use Illuminate\Http\Request;

/** @mixin PromoCode */
class PromoCodeResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'discount_type' => $this->discount_type->value,
            'discount_value' => $this->discount_value,
            'min_order_amount' => $this->min_order_amount,
            'max_uses' => $this->max_uses,
            'used_count' => $this->used_count,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
