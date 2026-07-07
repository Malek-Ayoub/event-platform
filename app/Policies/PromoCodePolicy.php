<?php

namespace App\Policies;

use App\Models\PromoCode;
use App\Models\User;

class PromoCodePolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'discounts.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, PromoCode $promoCode): bool
    {
        return $this->canView($user, $promoCode);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, PromoCode $promoCode): bool
    {
        return $this->canManage($user, $promoCode, self::PERMISSION);
    }

    public function delete(User $user, PromoCode $promoCode): bool
    {
        return $this->canManage($user, $promoCode, self::PERMISSION);
    }
}
