<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy extends TenantResourcePolicy
{
    /** @todo Phase 4.x+: introduce `categories.manage` when Marketplace / Event Catalog is split from events. */
    private const PERMISSION = 'events.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Category $category): bool
    {
        return $this->canView($user, $category);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->canManage($user, $category, self::PERMISSION);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->canManage($user, $category, self::PERMISSION);
    }
}
