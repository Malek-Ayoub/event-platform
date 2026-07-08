<?php

namespace App\Http\Requests\Commerce;

use App\Models\Product;

trait ResolvesRouteProduct
{
    public function routeProduct(): ?Product
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return $product;
        }

        if (is_numeric($product)) {
            return Product::query()->find((int) $product);
        }

        return null;
    }
}
