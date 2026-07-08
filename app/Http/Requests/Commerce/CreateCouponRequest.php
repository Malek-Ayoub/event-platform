<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\CreateCouponDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Coupon;

class CreateCouponRequest extends BaseApiRequest
{
    use ValidatesDiscountFields;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Coupon::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return CreateCouponDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:50'],
        ], $this->discountFieldRules());
    }
}
