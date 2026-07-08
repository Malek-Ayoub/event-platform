<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\BaseApiRequest;

class DeleteProductVariantRequest extends BaseApiRequest
{
    use ResolvesRouteProductVariant;

    public function authorize(): bool
    {
        $variant = $this->routeProductVariant();

        return $variant !== null && ($this->user()?->can('delete', $variant) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
