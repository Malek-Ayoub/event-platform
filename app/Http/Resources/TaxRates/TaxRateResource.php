<?php

namespace App\Http\Resources\TaxRates;

use App\Http\Resources\ApiResource;
use App\Models\TaxRate;
use Illuminate\Http\Request;

/** @mixin TaxRate */
class TaxRateResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rate' => $this->rate,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
