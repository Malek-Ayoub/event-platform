<?php

namespace App\Http\Resources\Orders;

use App\Http\Resources\ApiResource;
use App\Models\Ticket;
use Illuminate\Http\Request;

/** @mixin Ticket */
class TicketResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'order_id' => $this->order_id,
            'ticket_type_id' => $this->ticket_type_id,
            'serial' => $this->serial,
            'status' => $this->status->value,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
