<?php

namespace App\Services\Settlements\Support;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;

final class SettlementPaymentContextResolver
{
    public function resolveForOrder(Order $order, ?int $paymentTransactionId = null): array
    {
        if ($paymentTransactionId !== null) {
            $payment = PaymentTransaction::query()->whereKey($paymentTransactionId)->firstOrFail();

            return [
                'payment_transaction_id' => $payment->id,
                'currency' => (string) $payment->currency,
            ];
        }

        $payment = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [
                PaymentTransactionStatus::Completed,
                PaymentTransactionStatus::Paid,
            ])
            ->orderByDesc('id')
            ->first();

        return [
            'payment_transaction_id' => $payment?->id,
            'currency' => (string) ($payment?->currency ?? 'USD'),
        ];
    }
}
