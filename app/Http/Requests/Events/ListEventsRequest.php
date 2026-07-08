<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\Event;

class ListEventsRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Event::class) ?? false;
    }
}
