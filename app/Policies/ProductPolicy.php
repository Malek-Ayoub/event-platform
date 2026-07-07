<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'products.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->canView($user, $product);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->canManage($user, $product, self::PERMISSION);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->canManage($user, $product, self::PERMISSION);
    }
}
