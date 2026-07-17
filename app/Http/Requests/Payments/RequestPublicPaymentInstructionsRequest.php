<?php

namespace App\Http\Requests\Payments;

use App\Enums\OrdersDomain\OrderStatus;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Order;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RequestPublicPaymentInstructionsRequest extends BaseApiRequest
{
    private ?Order $resolvedOrder = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $orderNumber = $this->route('orderNumber');

        if (is_string($orderNumber)) {
            $this->merge(['order_number' => $orderNumber]);
        }
    }

    public function orderNumber(): string
    {
        return (string) $this->validated('order_number');
    }

    /**
     * Pending order in the current tenant, or 404 (missing / other venue / non-pending).
     */
    public function resolvedOrder(): Order
    {
        if ($this->resolvedOrder !== null) {
            return $this->resolvedOrder;
        }

        $order = Order::query()
            ->where('order_number', $this->orderNumber())
            ->where('status', OrderStatus::Pending)
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('Payable order not found.');
        }

        return $this->resolvedOrder = $order;
    }
}
