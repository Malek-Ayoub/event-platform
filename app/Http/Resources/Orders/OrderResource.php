<?php

namespace App\Http\Resources\Orders;

use App\Http\Resources\ApiResource;
use App\Http\Resources\Events\EventResource;
use App\Models\Order;
use Illuminate\Http\Request;

/** @mixin Order */
class OrderResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event' => $this->whenLoaded('event', fn () => new EventResource($this->event)),
            'customer_user_id' => $this->customer_user_id,
            'order_number' => $this->order_number,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'total' => $this->total,
            'commission_amount' => $this->commission_amount,
            'coupon_id' => $this->coupon_id,
            'promo_code_id' => $this->promo_code_id,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'status' => $this->status->value,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'tickets' => $this->whenLoaded('tickets', fn () => TicketResource::collection($this->tickets)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
