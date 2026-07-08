<?php

namespace App\Http\Resources\Events;

use App\Http\Resources\ApiResource;
use App\Models\TicketType;
use Illuminate\Http\Request;

/** @mixin TicketType */
class TicketTypeResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'quantity_sold' => $this->quantity_sold,
            'sale_start' => $this->sale_start?->toIso8601String(),
            'sale_end' => $this->sale_end?->toIso8601String(),
            'benefits' => $this->benefits,
            'color' => $this->color,
            'version' => $this->version,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
