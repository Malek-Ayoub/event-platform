<?php

namespace App\Http\Requests\Payments;

use App\Http\Requests\Api\BaseApiRequest;

class ShowPaymentRequest extends BaseApiRequest
{
    use ResolvesRoutePaymentTransaction;

    public function authorize(): bool
    {
        $payment = $this->routePaymentTransaction();

        return $payment !== null && ($this->user()?->can('view', $payment) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
