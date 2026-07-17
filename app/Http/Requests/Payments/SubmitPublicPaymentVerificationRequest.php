<?php

namespace App\Http\Requests\Payments;

use App\DTOs\BaseDTO;
use App\DTOs\Payments\VerifyPaymentDTO;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Http\Requests\Api\BaseApiRequest;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubmitPublicPaymentVerificationRequest extends BaseApiRequest
{
    private ?PaymentTransaction $resolvedPayment = null;

    public function authorize(): bool
    {
        return true;
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
            'order_number' => ['required', 'string', 'max:255'],
            'transaction_number' => ['required', 'string', 'max:255'],
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
     * Latest AwaitingTransfer/Verifying payment for the order, or 404.
     */
    public function resolvedPaymentTransaction(): PaymentTransaction
    {
        if ($this->resolvedPayment !== null) {
            return $this->resolvedPayment;
        }

        $order = Order::query()
            ->where('order_number', $this->orderNumber())
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        $payment = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                PaymentTransactionStatus::AwaitingTransfer,
                PaymentTransactionStatus::Verifying,
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($payment === null) {
            throw new NotFoundHttpException('Active payment instruction not found.');
        }

        return $this->resolvedPayment = $payment;
    }
}
