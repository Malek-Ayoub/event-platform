<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\Product;

class ListProductsRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }
}
