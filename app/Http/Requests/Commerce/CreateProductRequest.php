<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\CreateProductDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Product;

class CreateProductRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateProductDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
