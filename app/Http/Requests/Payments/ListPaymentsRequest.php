<?php

namespace App\Http\Requests\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Http\Requests\Api\PaginatedListRequest;
use App\Models\PaymentTransaction;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends PaginatedListRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PaymentTransaction::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'order_id' => ['sometimes', 'integer', $this->tenantExists('orders')],
            'status' => ['sometimes', 'string', Rule::enum(PaymentTransactionStatus::class)],
        ]);
    }

    public function orderId(): ?int
    {
        $value = $this->validated('order_id');

        return $value !== null ? (int) $value : null;
    }

    public function status(): ?PaymentTransactionStatus
    {
        $value = $this->validated('status');

        return $value !== null ? PaymentTransactionStatus::from((string) $value) : null;
    }
}
