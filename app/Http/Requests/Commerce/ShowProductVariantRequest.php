<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\BaseApiRequest;

class ShowProductVariantRequest extends BaseApiRequest
{
    use ResolvesRouteProductVariant;

    public function authorize(): bool
    {
        $variant = $this->routeProductVariant();

        return $variant !== null && ($this->user()?->can('view', $variant) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
