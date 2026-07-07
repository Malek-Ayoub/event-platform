<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;

class TaxRatePolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'venue.settings.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $this->canView($user, $taxRate);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $this->canManage($user, $taxRate, self::PERMISSION);
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $this->canManage($user, $taxRate, self::PERMISSION);
    }
}
