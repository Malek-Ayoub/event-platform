<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\CreateProductVariantDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\ProductVariant;

class CreateProductVariantRequest extends BaseApiRequest
{
    protected function prepareForValidation(): void
    {
        $product = $this->route('product');

        if (is_object($product) && method_exists($product, 'getKey')) {
            $this->merge(['product_id' => $product->getKey()]);
        } elseif (is_numeric($product)) {
            $this->merge(['product_id' => (int) $product]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductVariant::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateProductVariantDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', $this->tenantExists('products')],
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'price_override' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
