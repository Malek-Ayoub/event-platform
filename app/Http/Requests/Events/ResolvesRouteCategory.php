<?php

namespace App\Http\Requests\Events;

use App\Models\Category;

trait ResolvesRouteCategory
{
    public function routeCategory(): ?Category
    {
        $category = $this->route('category');

        if ($category instanceof Category) {
            return $category;
        }

        if (is_numeric($category)) {
            return Category::query()->find((int) $category);
        }

        return null;
    }
}
