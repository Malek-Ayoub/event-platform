<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\UpdateProductVariantDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateProductVariantRequest extends BaseApiRequest
{
    use ResolvesRouteProductVariant;

    public function authorize(): bool
    {
        $variant = $this->routeProductVariant();

        return $variant !== null && ($this->user()?->can('update', $variant) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateProductVariantDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
