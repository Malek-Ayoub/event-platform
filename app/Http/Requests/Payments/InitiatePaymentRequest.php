<?php

namespace App\Http\Requests\Payments;

use App\DTOs\BaseDTO;
use App\DTOs\Payments\InitiatePaymentDTO;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\PaymentTransaction;

class InitiatePaymentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PaymentTransaction::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return InitiatePaymentDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', $this->tenantExists('orders')],
            'provider' => ['required', 'string', 'max:50'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
