<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\PaginatedListRequest;

class ListProductVariantsRequest extends PaginatedListRequest
{
    use ResolvesRouteProduct;

    public function authorize(): bool
    {
        $product = $this->routeProduct();

        return $product !== null && ($this->user()?->can('view', $product) ?? false);
    }
}
