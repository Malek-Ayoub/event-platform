<?php

namespace App\Http\Requests\Commerce;

use App\Models\Coupon;

trait ResolvesRouteCoupon
{
    public function routeCoupon(): ?Coupon
    {
        $coupon = $this->route('coupon');

        if ($coupon instanceof Coupon) {
            return $coupon;
        }

        if (is_numeric($coupon)) {
            return Coupon::query()->find((int) $coupon);
        }

        return null;
    }
}
