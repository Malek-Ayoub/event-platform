<?php

namespace App\Services\Refunds;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Refunds\OrderNotRefundableException;
use App\Exceptions\Refunds\RefundAmountExceedsOrderException;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\ActivityLogService;
use App\Services\OutboxService;
use App\Services\Refunds\Data\CreateRefundData;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\TransactionRunner;

class RefundService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private RefundStateMachine $stateMachine,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function createRefund(CreateRefundData $data): Refund
    {
        return $this->transactionRunner->run(function () use ($data): Refund {
            $order = Order::query()
                ->whereKey($data->orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                throw OrderNotRefundableException::forOrder((int) $order->id, $order->status);
            }

            $this->assertRefundAmountAvailable($order, $this->formatAmount($data->amount));

            if ($data->paymentTransactionId !== null) {
                $payment = PaymentTransaction::query()
                    ->whereKey($data->paymentTransactionId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($payment->order_id !== $order->id) {
                    throw new \InvalidArgumentException(
                        "Payment transaction {$payment->id} does not belong to order {$order->id}.",
                    );
                }
            }

            $refund = Refund::query()->create([
                'venue_id' => $order->venue_id,
                'order_id' => $order->id,
                'payment_transaction_id' => $data->paymentTransactionId,
                'amount' => $this->formatAmount($data->amount),
                'status' => RefundStatus::Pending,
                'reason' => $data->reason,
            ]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $refund,
                action: 'created',
                newValues: [
                    'order_id' => $refund->order_id,
                    'amount' => $refund->amount,
                    'status' => $refund->status->value,
                ],
                changedFields: ['order_id', 'amount', 'status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'refund.created',
                aggregate: $refund,
                payload: [
                    'order_id' => $refund->order_id,
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount,
                ],
            );

            return $refund;
        });
    }

    public function processRefund(ProcessRefundData $data): Refund
    {
        return $this->transactionRunner->run(function () use ($data): Refund {
            $refund = Refund::query()
                ->whereKey($data->refundId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status === RefundStatus::Processed) {
                return $refund->fresh();
            }

            $this->stateMachine->assertCanTransition(
                $refund->status,
                RefundStatus::Processed,
            );

            $order = Order::query()
                ->whereKey($refund->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $refund->update([
                'status' => RefundStatus::Processed,
                'provider_refund_id' => $data->providerRefundId,
                'processed_at' => now(),
            ]);

            $totalRefunded = $this->totalProcessedRefundAmount((int) $order->id);

            if (bccomp($totalRefunded, $this->formatAmount($order->total), 2) >= 0) {
                $order->update(['status' => OrderStatus::Refunded]);

                if ($refund->payment_transaction_id !== null) {
                    $payment = PaymentTransaction::query()
                        ->whereKey($refund->payment_transaction_id)
                        ->lockForUpdate()
                        ->first();

                    if ($payment !== null && $payment->status === PaymentTransactionStatus::Completed) {
                        $payment->update(['status' => PaymentTransactionStatus::Refunded]);
                    }
                }
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $refund,
                action: 'processed',
                newValues: [
                    'order_id' => $refund->order_id,
                    'status' => RefundStatus::Processed->value,
                    'processed_at' => $refund->processed_at?->toIso8601String(),
                ],
                changedFields: ['status', 'processed_at'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'refund.processed',
                aggregate: $refund,
                payload: [
                    'order_id' => $refund->order_id,
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount,
                ],
            );

            return $refund->fresh();
        });
    }

    private function assertRefundAmountAvailable(Order $order, string $requestedAmount): void
    {
        $alreadyReserved = Refund::query()
            ->where('order_id', $order->id)
            ->whereIn('status', [RefundStatus::Pending, RefundStatus::Processed])
            ->get()
            ->reduce(
                fn (string $carry, Refund $refund): string => bcadd($carry, $this->formatAmount($refund->amount), 2),
                '0.00',
            );

        $available = bcsub($this->formatAmount($order->total), $alreadyReserved, 2);

        if (bccomp($requestedAmount, $available, 2) === 1) {
            throw RefundAmountExceedsOrderException::forOrder(
                (int) $order->id,
                $requestedAmount,
                $available,
            );
        }
    }

    private function totalProcessedRefundAmount(int $orderId): string
    {
        return Refund::query()
            ->where('order_id', $orderId)
            ->where('status', RefundStatus::Processed)
            ->get()
            ->reduce(
                fn (string $carry, Refund $refund): string => bcadd($carry, $this->formatAmount($refund->amount), 2),
                '0.00',
            );
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
