<?php

namespace App\Http\Requests\Commissions;

use App\DTOs\BaseDTO;
use App\DTOs\Commissions\RecordCommissionPaymentDTO;
use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\CommissionPayment;
use Illuminate\Validation\Rule;

class RecordCommissionPaymentRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CommissionPayment::class) ?? false;
    }

    /**
     * @return class-string<BaseDTO>
     */
    protected function dtoClass(): ?string
    {
        return RecordCommissionPaymentDTO::class;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $venueId = (int) $this->input('venue_id');

        return [
            'venue_id' => ['required', 'integer', Rule::exists('venues', 'id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_method' => ['required', Rule::enum(CommissionPaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:128'],
            'received_at' => ['required', 'date'],
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_accounts', 'id')->where('venue_id', $venueId),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
