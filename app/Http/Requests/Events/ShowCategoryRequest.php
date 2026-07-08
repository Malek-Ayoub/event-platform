<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\Api\BaseApiRequest;

class ShowCategoryRequest extends BaseApiRequest
{
    use ResolvesRouteCategory;

    public function authorize(): bool
    {
        $category = $this->routeCategory();

        return $category !== null && ($this->user()?->can('view', $category) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
