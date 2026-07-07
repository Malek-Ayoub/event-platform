<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'discounts.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $this->canView($user, $coupon);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $this->canManage($user, $coupon, self::PERMISSION);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->canManage($user, $coupon, self::PERMISSION);
    }
}
