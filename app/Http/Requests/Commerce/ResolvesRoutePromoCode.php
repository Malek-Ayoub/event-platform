<?php

namespace App\Http\Requests\Commerce;

use App\Models\PromoCode;

trait ResolvesRoutePromoCode
{
    public function routePromoCode(): ?PromoCode
    {
        $promoCode = $this->route('promoCode');

        if ($promoCode instanceof PromoCode) {
            return $promoCode;
        }

        if (is_numeric($promoCode)) {
            return PromoCode::query()->find((int) $promoCode);
        }

        return null;
    }
}
