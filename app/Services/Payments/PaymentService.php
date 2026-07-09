<?php

namespace App\Services\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Payments\DuplicateTransactionNumberException;
use App\Exceptions\Payments\OrderNotPayableException;
use App\Exceptions\Payments\PaymentAmountMismatchException;
use App\Exceptions\Payments\PaymentProviderMismatchException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Payments\Data\BeginVerificationData;
use App\Services\Payments\Data\CompletePaymentData;
use App\Services\Payments\Data\CreateAwaitingTransferData;
use App\Services\Payments\Data\ExpirePaymentData;
use App\Services\Payments\Data\FailPaymentData;
use App\Services\Payments\Data\InitiatePaymentData;
use App\Services\Payments\Data\MarkPaidData;
use App\Services\Payments\Data\MarkVerificationFailedData;
use App\Services\TransactionRunner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class PaymentService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private PaymentTransactionStateMachine $stateMachine,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function list(int $perPage = 15, ?int $orderId = null, ?PaymentTransactionStatus $status = null): LengthAwarePaginator
    {
        return PaymentTransaction::query()
            ->when($orderId !== null, fn ($query) => $query->where('order_id', $orderId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getPayment(PaymentTransaction $payment): PaymentTransaction
    {
        return PaymentTransaction::query()
            ->whereKey($payment->id)
            ->with(['order'])
            ->firstOrFail();
    }

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
                throw PaymentAmountMismatchException::forOrder(
                    (int) $order->id,
                    $this->formatAmount($data->amount),
                    $this->formatAmount($order->total),
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

    public function createAwaitingTransfer(CreateAwaitingTransferData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $provider = strtolower(trim($data->provider));

            $existing = PaymentTransaction::query()
                ->where('order_id', $data->orderId)
                ->where('provider', $provider)
                ->where('status', PaymentTransactionStatus::AwaitingTransfer)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
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
                throw PaymentAmountMismatchException::forOrder(
                    (int) $order->id,
                    $this->formatAmount($data->amount),
                    $this->formatAmount($order->total),
                );
            }

            $payment = PaymentTransaction::query()->create([
                'venue_id' => $order->venue_id,
                'order_id' => $order->id,
                'provider' => $provider,
                'provider_transaction_id' => null,
                'transaction_number' => null,
                'amount' => $this->formatAmount($data->amount),
                'currency' => strtoupper($data->currency),
                'status' => PaymentTransactionStatus::AwaitingTransfer,
                'expires_at' => Carbon::instance($data->expiresAt),
                'payload' => null,
            ]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'awaiting_transfer',
                newValues: [
                    'order_id' => $payment->order_id,
                    'provider' => $payment->provider,
                    'amount' => $payment->amount,
                    'status' => $payment->status->value,
                    'expires_at' => $payment->expires_at?->toIso8601String(),
                ],
                changedFields: ['order_id', 'provider', 'amount', 'status', 'expires_at'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.awaiting_transfer',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'provider' => $payment->provider,
                    'amount' => $payment->amount,
                    'expires_at' => $payment->expires_at?->toIso8601String(),
                ],
            );

            return $payment;
        });
    }

    public function beginVerification(BeginVerificationData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $payment = PaymentTransaction::query()
                ->whereKey($data->paymentTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            $transactionNumber = $this->normalizeTransactionNumber($data->transactionNumber);

            if ($payment->status === PaymentTransactionStatus::Verifying
                && $payment->transaction_number === $transactionNumber) {
                return $payment->fresh();
            }

            $this->stateMachine->assertCanTransition(
                $payment->status,
                PaymentTransactionStatus::Verifying,
            );

            try {
                $payment->update([
                    'status' => PaymentTransactionStatus::Verifying,
                    'transaction_number' => $transactionNumber,
                ]);
            } catch (QueryException $exception) {
                if ($this->transactionNumberUsedByAnotherPayment($transactionNumber, (int) $payment->id)) {
                    throw DuplicateTransactionNumberException::forTransactionNumber($transactionNumber);
                }

                throw $exception;
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'verifying',
                newValues: [
                    'order_id' => $payment->order_id,
                    'status' => PaymentTransactionStatus::Verifying->value,
                    'transaction_number' => $transactionNumber,
                ],
                changedFields: ['status', 'transaction_number'],
                ipAddress: $data->ipAddress,
            );

            return $payment->fresh();
        });
    }

    public function markPaid(MarkPaidData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $payment = PaymentTransaction::query()
                ->whereKey($data->paymentTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PaymentTransactionStatus::Paid) {
                return $payment->fresh();
            }

            $this->stateMachine->assertCanTransition(
                $payment->status,
                PaymentTransactionStatus::Paid,
            );

            $order = Order::query()
                ->whereKey($payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::Pending) {
                throw OrderNotPayableException::forOrder((int) $order->id, $order->status);
            }

            $payment->update([
                'status' => PaymentTransactionStatus::Paid,
                'provider_transaction_id' => $data->providerTransactionId,
            ]);

            $order->update([
                'status' => OrderStatus::Paid,
                'payment_method' => $payment->provider,
                'payment_reference' => $data->providerTransactionId,
            ]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'paid',
                newValues: [
                    'order_id' => $payment->order_id,
                    'status' => PaymentTransactionStatus::Paid->value,
                    'provider_transaction_id' => $data->providerTransactionId,
                ],
                changedFields: ['status', 'provider_transaction_id'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.paid',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'payment_transaction_id' => $payment->id,
                    'amount' => $payment->amount,
                    'provider_transaction_id' => $data->providerTransactionId,
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

    public function markVerificationFailed(MarkVerificationFailedData $data): PaymentTransaction
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
                action: 'verification_failed',
                newValues: [
                    'order_id' => $payment->order_id,
                    'status' => PaymentTransactionStatus::Failed->value,
                    'reason' => $data->reason->value,
                ],
                changedFields: ['status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.verification_failed',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'payment_transaction_id' => $payment->id,
                    'reason' => $data->reason->value,
                ],
            );

            return $payment->fresh();
        });
    }

    public function expirePayment(ExpirePaymentData $data): PaymentTransaction
    {
        return $this->transactionRunner->run(function () use ($data): PaymentTransaction {
            $payment = PaymentTransaction::query()
                ->whereKey($data->paymentTransactionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PaymentTransactionStatus::Expired) {
                return $payment->fresh();
            }

            $this->stateMachine->assertCanTransition(
                $payment->status,
                PaymentTransactionStatus::Expired,
            );

            $payment->update(['status' => PaymentTransactionStatus::Expired]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $payment,
                action: 'expired',
                newValues: [
                    'order_id' => $payment->order_id,
                    'status' => PaymentTransactionStatus::Expired->value,
                ],
                changedFields: ['status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'payment.expired',
                aggregate: $payment,
                payload: [
                    'order_id' => $payment->order_id,
                    'payment_transaction_id' => $payment->id,
                ],
            );

            return $payment->fresh();
        });
    }

    private function normalizeTransactionNumber(string $transactionNumber): string
    {
        return trim($transactionNumber);
    }

    private function transactionNumberUsedByAnotherPayment(string $transactionNumber, int $excludePaymentId): bool
    {
        return PaymentTransaction::query()
            ->withoutGlobalScopes()
            ->where('transaction_number', $transactionNumber)
            ->whereKeyNot($excludePaymentId)
            ->exists();
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
