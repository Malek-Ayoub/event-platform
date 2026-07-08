<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\PaginatedListRequest;

class ListTicketTypesRequest extends PaginatedListRequest
{
    use ResolvesRouteEvent;

    public function authorize(): bool
    {
        $event = $this->routeEvent();

        return $event !== null && ($this->user()?->can('view', $event) ?? false);
    }
}
