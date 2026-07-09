<?php

namespace App\Http\Requests\Payments;

use App\DTOs\BaseDTO;
use App\DTOs\Payments\VerifyPaymentDTO;
use App\Http\Requests\Api\BaseApiRequest;

class VerifyPaymentRequest extends BaseApiRequest
{
    use ResolvesRoutePaymentTransaction;

    public function authorize(): bool
    {
        $payment = $this->routePaymentTransaction();

        return $payment !== null && ($this->user()?->can('update', $payment) ?? false);
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return VerifyPaymentDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transaction_number' => ['required', 'string', 'max:255'],
        ];
    }
}
