<?php

namespace App\Services\Payments;

use App\Enums\Payments\PaymentWalletProvider;
use App\Exceptions\Payments\PaymentAccountLockedException;
use App\Models\EventPaymentAccount;
use App\Models\Order;
use App\Models\PaymentAccount;

/**
 * Enforces immutability rules for payment accounts once sales have started.
 *
 * - Credentials (provider, account_identifier) are locked when any order references the account.
 * - Event links cannot be removed once the event has at least one order.
 * - New accounts may still be linked and marked default for future orders.
 */
final class PaymentAccountGuard
{
    public function assertCredentialsMutable(PaymentAccount $account): void
    {
        if ($this->hasOrdersReferencingAccount((int) $account->id)) {
            throw PaymentAccountLockedException::becauseOrdersReferenceAccount((int) $account->id);
        }
    }

    public function assertCanUpdateCredentials(
        PaymentAccount $account,
        PaymentWalletProvider $provider,
        string $accountIdentifier,
    ): void {
        if ($account->provider === $provider && $account->account_identifier === $accountIdentifier) {
            return;
        }

        $this->assertCredentialsMutable($account);
    }

    public function assertCanUnlinkFromEvent(EventPaymentAccount $link): void
    {
        if ($this->eventHasOrders((int) $link->event_id)) {
            throw PaymentAccountLockedException::becauseEventHasOrders((int) $link->event_id);
        }
    }

    public function hasOrdersReferencingAccount(int $paymentAccountId): bool
    {
        return Order::query()
            ->where('payment_account_id', $paymentAccountId)
            ->exists();
    }

    public function eventHasOrders(int $eventId): bool
    {
        return Order::query()
            ->where('event_id', $eventId)
            ->exists();
    }
}
