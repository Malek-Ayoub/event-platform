<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\Category;

class ListCategoriesRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Category::class) ?? false;
    }
}
