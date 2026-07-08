<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\BaseApiRequest;

class ShowCouponRequest extends BaseApiRequest
{
    use ResolvesRouteCoupon;

    public function authorize(): bool
    {
        $coupon = $this->routeCoupon();

        return $coupon !== null && ($this->user()?->can('view', $coupon) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
