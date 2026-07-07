<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'orders.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Order $order): bool
    {
        return $this->canView($user, $order);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->canManage($user, $order, self::PERMISSION);
    }

    public function delete(User $user, Order $order): bool
    {
        return $this->canManage($user, $order, self::PERMISSION);
    }
}
