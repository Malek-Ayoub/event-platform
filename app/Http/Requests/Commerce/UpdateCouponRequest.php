<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\UpdateCouponDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdateCouponRequest extends BaseApiRequest
{
    use ResolvesRouteCoupon;
    use ValidatesDiscountFields;

    public function authorize(): bool
    {
        $coupon = $this->routeCoupon();

        return $coupon !== null && ($this->user()?->can('update', $coupon) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdateCouponDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['sometimes', 'string', 'max:50'],
        ], $this->discountFieldRules(required: false));
    }
}
