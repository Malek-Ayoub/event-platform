<?php

namespace App\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Payments\OrderNotPayableException;
use App\Exceptions\Payments\PaymentProviderMismatchException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\FailPaymentData;
use App\Services\Payments\Data\InitiatePaymentData;
use App\Services\TransactionRunner;
use Illuminate\Database\QueryException;

class PaymentService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private PaymentTransactionStateMachine $stateMachine,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function initiatePayment(InitiatePaymentData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $existing = PaymentTransaction::query()
                ->where('provider', $data->provider)
                ->where('provider_transaction_id', $data->providerTransactionId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->order_id !== $data->orderId) {
                    throw PaymentProviderMismatchException::forProviderTransaction(
                        $data->provider,
                        $data->providerTransactionId,
                        $data->orderId,
                        (int) $existing->order_id,
                    );
                }

                return $existing;
            }

            $order = Order::query()
                ->whereKey($data->orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::Pending) {
                throw OrderNotPayableException::forOrder((int) $order->id, $order->status);
            }

            if (bccomp($this->formatAmount($data->amount), $this->formatAmount($order->total), 2) !== 0) {
                throw new \InvalidArgumentException(
                    "Payment amount {$data->amount} must match order total {$order->total}.",
                );
            }

            try {
                $payment = PaymentTransaction::query()->create([
                    'venue_id' => $order->venue_id,
                    'order_id' => $order->id,
                    'provider' => $data->provider,
                    'provider_transaction_id' => $data->providerTransactionId,
                    'amount' => $this->formatAmount($data->amount),
                    'currency' => $data->currency,
                    'status' => PaymentTransactionStatus::Pending,
                    'payload' => $data->payload,
                ]);
            } catch (QueryException) {
                $existing = PaymentTransaction::query()
                    ->where('provider', $data->provider)
                    ->where('provider_transaction_id', $data->providerTransactionId)
                    ->firstOrFail();

                if ($existing->order_id !== $data->orderId) {
                    throw PaymentProviderMismatchException::forProviderTransaction(
                        $data->provider,
                        $data->providerTransactionId,
                        $data->orderId,
                        (int) $existing->order_id,
                    );
                }

                return $existing;
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'initiated',
                newValues: [
                    'order_id' => $payment->order_id,
                    'provider' => $payment->provider,
                    'provider_transaction_id' => $payment->provider_transaction_id,
                    'amount' => $payment->amount,
                    'status' => $payment->status->value,
                ],
                changedFields: ['order_id', 'provider', 'provider_transaction_id', 'amount', 'status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.initiated',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'provider' => $payment->provider,
                    'provider_transaction_id' => $payment->provider_transaction_id,
                    'amount' => $payment->amount,
                ],
            );

            return $payment;
        });
    }

    public function completePayment(CompletePaymentData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $payment = PaymentTransaction::query()
                ->whereKey($data->paymentTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PaymentTransactionStatus::Completed) {
                return $payment->fresh();
            }

            $this->stateMachine->assertCanTransition(
                $payment->status,
                PaymentTransactionStatus::Completed,
            );

            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::Pending) {
                throw OrderNotPayableException::forOrder((int) $order->id, $order->status);
            }

            $payment->update(['status' => PaymentTransactionStatus::Completed]);

            $order->update([
                'status' => OrderStatus::Paid,
                'payment_method' => $data->paymentMethod ?? $order->payment_method,
                'payment_reference' => $data->paymentReference ?? $payment->provider_transaction_id,
            ]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'completed',
                newValues: [
                    'order_id' => $payment->order_id,
                    'status' => PaymentTransactionStatus::Completed->value,
                ],
                changedFields: ['status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.completed',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'payment_transaction_id' => $payment->id,
                    'amount' => $payment->amount,
                ],
            );

            $this->outboxService->record(
                eventType: 'order.paid',
                aggregate: $order->fresh(),
                payload: [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_transaction_id' => $payment->id,
                ],
            );

            return $payment->fresh();
        });
    }

    public function failPayment(FailPaymentData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $payment = PaymentTransaction::query()
                ->whereKey($data->paymentTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PaymentTransactionStatus::Failed) {
                return $payment->fresh();
            }

            $this->stateMachine->assertCanTransition(
                $payment->status,
                PaymentTransactionStatus::Failed,
            );

            $payment->update(['status' => PaymentTransactionStatus::Failed]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'failed',
                newValues: [
                    'order_id' => $payment->order_id,
                    'status' => PaymentTransactionStatus::Failed->value,
                    'reason' => $data->reason,
                ],
                changedFields: ['status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.failed',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'payment_transaction_id' => $payment->id,
                    'reason' => $data->reason,
                ],
            );

            return $payment->fresh();
        });
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
