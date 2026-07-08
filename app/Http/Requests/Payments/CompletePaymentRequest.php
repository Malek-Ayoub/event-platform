<?php

namespace App\Http\Requests\Payments;

use App\DTOs\BaseDTO;
use App\DTOs\Payments\CompletePaymentDTO;
use App\Http\Requests\Api\BaseApiRequest;

class CompletePaymentRequest extends BaseApiRequest
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
        return CompletePaymentDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['nullable', 'string', 'max:50'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
