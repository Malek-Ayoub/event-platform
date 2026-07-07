<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\User;

class ProductVariantPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'products.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, ProductVariant $productVariant): bool
    {
        return $this->canView($user, $productVariant);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, ProductVariant $productVariant): bool
    {
        return $this->canManage($user, $productVariant, self::PERMISSION);
    }

    public function delete(User $user, ProductVariant $productVariant): bool
    {
        return $this->canManage($user, $productVariant, self::PERMISSION);
    }
}
