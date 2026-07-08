<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\UpdateProductDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateProductRequest extends BaseApiRequest
{
    use ResolvesRouteProduct;

    public function authorize(): bool
    {
        $product = $this->routeProduct();

        return $product !== null && ($this->user()?->can('update', $product) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateProductDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'event_id' => ['sometimes', 'nullable', 'integer', $this->tenantExists('events')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
