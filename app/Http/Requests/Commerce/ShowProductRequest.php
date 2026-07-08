<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\BaseApiRequest;

class ShowProductRequest extends BaseApiRequest
{
    use ResolvesRouteProduct;

    public function authorize(): bool
    {
        $product = $this->routeProduct();

        return $product !== null && ($this->user()?->can('view', $product) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
