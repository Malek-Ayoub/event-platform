<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'refunds.process';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Refund $refund): bool
    {
        return $this->canView($user, $refund);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Refund $refund): bool
    {
        return $this->canManage($user, $refund, self::PERMISSION);
    }

    public function delete(User $user, Refund $refund): bool
    {
        return $this->canManage($user, $refund, self::PERMISSION);
    }
}
