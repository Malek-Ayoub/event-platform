<?php

namespace App\Http\Requests\Commerce;

use App\Models\ProductVariant;

trait ResolvesRouteProductVariant
{
    public function routeProductVariant(): ?ProductVariant
    {
        $variant = $this->route('productVariant');

        if ($variant instanceof ProductVariant) {
            return $variant;
        }

        if (is_numeric($variant)) {
            return ProductVariant::query()->find((int) $variant);
        }

        return null;
    }
}
