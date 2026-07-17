<?php

namespace App\Http\Resources\Orders;

use App\Http\Resources\ApiResource;
use App\Models\Order;
use Illuminate\Http\Request;

/** @mixin Order */
class PublicOrderResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'total' => $this->total,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
        ];
    }
}
