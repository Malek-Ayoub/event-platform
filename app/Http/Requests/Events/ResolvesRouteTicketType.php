<?php

namespace App\Http\Requests\Events;

use App\Models\TicketType;

trait ResolvesRouteTicketType
{
    public function routeTicketType(): ?TicketType
    {
        $ticketType = $this->route('ticketType');

        if ($ticketType instanceof TicketType) {
            return $ticketType;
        }

        if (is_numeric($ticketType)) {
            return TicketType::query()->find((int) $ticketType);
        }

        return null;
    }
}
