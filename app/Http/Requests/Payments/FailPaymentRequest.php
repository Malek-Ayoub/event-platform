<?php

namespace App\Http\Requests\Payments;

use App\DTOs\BaseDTO;
use App\DTOs\Payments\FailPaymentDTO;
use App\Http\Requests\Api\BaseApiRequest;

class FailPaymentRequest extends BaseApiRequest
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
        return FailPaymentDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
