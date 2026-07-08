<?php

namespace App\Http\Requests\Commerce;

use App\Http\Requests\Api\BaseApiRequest;

class DeletePromoCodeRequest extends BaseApiRequest
{
    use ResolvesRoutePromoCode;

    public function authorize(): bool
    {
        $promoCode = $this->routePromoCode();

        return $promoCode !== null && ($this->user()?->can('delete', $promoCode) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
