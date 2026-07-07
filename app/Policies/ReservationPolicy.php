<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'reservations.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->canView($user, $reservation);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $this->canManage($user, $reservation, self::PERMISSION);
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $this->canManage($user, $reservation, self::PERMISSION);
    }
}
