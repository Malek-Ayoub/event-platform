<?php

namespace App\Policies;

use App\Models\CommissionPayment;
use App\Models\User;

class CommissionPaymentPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, CommissionPayment $commissionPayment): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
