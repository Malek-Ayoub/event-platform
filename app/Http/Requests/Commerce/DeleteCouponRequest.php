<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\BaseApiRequest;

class DeleteCouponRequest extends BaseApiRequest
{
    use ResolvesRouteCoupon;

    public function authorize(): bool
    {
        $coupon = $this->routeCoupon();

        return $coupon !== null && ($this->user()?->can('delete', $coupon) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
