<?php

namespace App\Services\Commissions;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Exceptions\Commissions\AdjustmentExceedsCommissionException;
use App\Exceptions\Commissions\CommissionNotFoundException;
use App\Exceptions\Commissions\OrderNotEligibleForCommissionException;
use App\Exceptions\Commissions\PaymentNotCompletedException;
use App\Exceptions\Commissions\RefundNotProcessedException;
use App\Models\Commission;
use App\Models\CommissionAdjustment;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Venue;
use App\Services\ActivityLogService;
use App\Services\Commissions\Data\RecordCommissionAdjustmentData;
use App\Services\Commissions\Data\RecordCommissionData;
use App\Services\OutboxService;
use App\Services\TransactionRunner;
use Illuminate\Database\QueryException;

class CommissionService
{
    public function __construct(
        private TransactionRunner $transactionRunner,
        private ActivityLogService $activityLogService,
        private OutboxService $outboxService,
    ) {}

    public function recordCommission(RecordCommissionData $data): Commission
    {
        return $this->transactionRunner->run(function () use ($data): Commission {
            $existing = Commission::query()
                ->where('order_id', $data->orderId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $order = Order::query()->whereKey($data->orderId)->firstOrFail();

            if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                throw OrderNotEligibleForCommissionException::forOrder((int) $order->id, $order->status);
            }

            $this->assertCompletedPaymentExists($order, $data->paymentTransactionId);

            $venue = Venue::query()->whereKey($order->venue_id)->firstOrFail();
            $rate = $this->formatRate($venue->commission_rate);
            $amount = $this->calculateCommissionAmount($this->formatAmount($order->total), $rate);

            try {
                $commission = Commission::query()->create([
                    'venue_id' => $order->venue_id,
                    'order_id' => $order->id,
                    'amount' => $amount,
                    'rate' => $rate,
                    'status' => CommissionStatus::Pending,
                ]);
            } catch (QueryException) {
                return Commission::query()->where('order_id', $data->orderId)->firstOrFail();
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $commission,
                action: 'recorded',
                newValues: [
                    'order_id' => $commission->order_id,
                    'amount' => $commission->amount,
                    'rate' => $commission->rate,
                    'status' => $commission->status->value,
                ],
                changedFields: ['order_id', 'amount', 'rate', 'status'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'commission.recorded',
                aggregate: $commission,
                payload: [
                    'order_id' => $commission->order_id,
                    'commission_id' => $commission->id,
                    'amount' => $commission->amount,
                    'rate' => $commission->rate,
                ],
            );

            return $commission;
        });
    }

    public function recordAdjustment(RecordCommissionAdjustmentData $data): CommissionAdjustment
    {
        return $this->transactionRunner->run(function () use ($data): CommissionAdjustment {
            $existing = CommissionAdjustment::query()
                ->where('refund_id', $data->refundId)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $refund = Refund::query()->whereKey($data->refundId)->firstOrFail();

            if ($refund->status !== RefundStatus::Processed) {
                throw RefundNotProcessedException::forRefund((int) $refund->id);
            }

            $commission = Commission::query()
                ->where('order_id', $refund->order_id)
                ->first();

            if ($commission === null) {
                throw CommissionNotFoundException::forOrder((int) $refund->order_id);
            }

            $rateSnapshot = $this->formatRate($commission->rate);
            $adjustmentAmount = $this->calculateCommissionAmount(
                $this->formatAmount($refund->amount),
                $rateSnapshot,
            );

            $alreadyAdjusted = CommissionAdjustment::query()
                ->where('commission_id', $commission->id)
                ->get()
                ->reduce(
                    fn (string $carry, CommissionAdjustment $adjustment): string => bcadd(
                        $carry,
                        $this->formatAmount($adjustment->adjustment_amount),
                        2,
                    ),
                    '0.00',
                );

            $remaining = bcsub($this->formatAmount($commission->amount), $alreadyAdjusted, 2);

            if (bccomp($adjustmentAmount, $remaining, 2) === 1) {
                throw AdjustmentExceedsCommissionException::forCommission(
                    (int) $commission->id,
                    $adjustmentAmount,
                    $remaining,
                );
            }

            try {
                $adjustment = CommissionAdjustment::query()->create([
                    'venue_id' => $commission->venue_id,
                    'commission_id' => $commission->id,
                    'refund_id' => $refund->id,
                    'adjustment_amount' => $adjustmentAmount,
                    'rate_snapshot' => $rateSnapshot,
                ]);
            } catch (QueryException) {
                return CommissionAdjustment::query()->where('refund_id', $data->refundId)->firstOrFail();
            }

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $adjustment,
                action: 'recorded',
                newValues: [
                    'commission_id' => $adjustment->commission_id,
                    'refund_id' => $adjustment->refund_id,
                    'adjustment_amount' => $adjustment->adjustment_amount,
                    'rate_snapshot' => $adjustment->rate_snapshot,
                ],
                changedFields: ['commission_id', 'refund_id', 'adjustment_amount', 'rate_snapshot'],
                ipAddress: $data->ipAddress,
            );

            $this->outboxService->record(
                eventType: 'commission.adjusted',
                aggregate: $adjustment,
                payload: [
                    'commission_id' => $adjustment->commission_id,
                    'refund_id' => $adjustment->refund_id,
                    'adjustment_amount' => $adjustment->adjustment_amount,
                    'rate_snapshot' => $adjustment->rate_snapshot,
                ],
            );

            return $adjustment;
        });
    }

    private function assertCompletedPaymentExists(Order $order, ?int $paymentTransactionId): void
    {
        if ($paymentTransactionId !== null) {
            $payment = PaymentTransaction::query()->whereKey($paymentTransactionId)->firstOrFail();

            if ($payment->order_id !== $order->id) {
                throw new \InvalidArgumentException(
                    "Payment transaction {$payment->id} does not belong to order {$order->id}.",
                );
            }

            if ($payment->status !== PaymentTransactionStatus::Completed) {
                throw PaymentNotCompletedException::forPaymentTransaction((int) $payment->id);
            }

            return;
        }

        $hasCompleted = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentTransactionStatus::Completed)
            ->exists();

        if (! $hasCompleted) {
            throw PaymentNotCompletedException::forOrder((int) $order->id);
        }
    }

    private function calculateCommissionAmount(string $baseAmount, string $rate): string
    {
        return bcmul($baseAmount, bcdiv($rate, '100', 4), 2);
    }

    private function formatAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function formatRate(mixed $rate): string
    {
        return number_format((float) $rate, 2, '.', '');
    }
}
