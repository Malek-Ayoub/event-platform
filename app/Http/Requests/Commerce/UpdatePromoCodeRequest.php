<?php

namespace App\Http\Requests\Commerce;

use App\DTOs\BaseDTO;
use App\DTOs\Commerce\UpdatePromoCodeDTO;
use App\Http\Requests\Api\BaseApiRequest;

class UpdatePromoCodeRequest extends BaseApiRequest
{
    use ResolvesRoutePromoCode;
    use ValidatesDiscountFields;

    public function authorize(): bool
    {
        $promoCode = $this->routePromoCode();

        return $promoCode !== null && ($this->user()?->can('update', $promoCode) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return UpdatePromoCodeDTO::class;
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
