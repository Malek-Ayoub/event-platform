<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;

class PaymentTransactionPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'orders.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $this->canView($user, $paymentTransaction);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $this->canManage($user, $paymentTransaction, self::PERMISSION);
    }

    public function delete(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $this->canManage($user, $paymentTransaction, self::PERMISSION);
    }
}
