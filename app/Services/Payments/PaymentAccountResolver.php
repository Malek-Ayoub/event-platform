<?php

namespace App\Services\Payments;

use App\Exceptions\Payments\PaymentAccountNotFoundException;
use App\Models\EventPaymentAccount;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\PaymentTransaction;

/**
 * Resolves merchant payment accounts for payment flows.
 *
 * Resolution paths:
 * - New orders: Event → EventPaymentAccount (default active) → PaymentAccount
 * - Existing orders: Order.payment_account_id (immutable snapshot)
 * - Payments: PaymentTransaction.payment_account_id → Order.payment_account_id
 */
final class PaymentAccountResolver
{
    public function resolveDefaultForEvent(int $eventId): PaymentAccount
    {
        $link = EventPaymentAccount::query()
            ->where('event_id', $eventId)
            ->active()
            ->default()
            ->with('paymentAccount')
            ->first();

        if ($link?->paymentAccount === null) {
            throw PaymentAccountNotFoundException::forEvent($eventId);
        }

        return $link->paymentAccount;
    }

    public function resolveForOrder(Order $order): PaymentAccount
    {
        if ($order->payment_account_id !== null) {
            $account = PaymentAccount::query()->whereKey($order->payment_account_id)->first();

            if ($account !== null) {
                return $account;
            }
        }

        return $this->resolveDefaultForEvent((int) $order->event_id);
    }

    public function resolveForPayment(PaymentTransaction $payment): PaymentAccount
    {
        if ($payment->payment_account_id !== null) {
            $account = PaymentAccount::query()->whereKey($payment->payment_account_id)->first();

            if ($account !== null) {
                return $account;
            }
        }

        $payment->loadMissing('order');

        if ($payment->order === null) {
            throw PaymentAccountNotFoundException::forPayment((int) $payment->id);
        }

        return $this->resolveForOrder($payment->order);
    }
}
