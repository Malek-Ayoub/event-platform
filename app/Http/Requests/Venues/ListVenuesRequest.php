<?php

namespace App\Http\Requests\Venues;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\Venue;

class ListVenuesRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Venue::class) ?? false;
    }
}
