<?php

namespace App\Http\Requests\Events;

use App\DTOs\BaseDTO;
use App\DTOs\Events\UpdateCategoryDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateCategoryRequest extends BaseApiRequest
{
    use ResolvesRouteCategory;

    public function authorize(): bool
    {
        $category = $this->routeCategory();

        return $category !== null && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateCategoryDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
