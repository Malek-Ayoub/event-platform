<?php

namespace App\Policies;

use App\Models\TicketType;
use App\Models\User;

class TicketTypePolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'ticket_types.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, TicketType $ticketType): bool
    {
        return $this->canView($user, $ticketType);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, TicketType $ticketType): bool
    {
        return $this->canManage($user, $ticketType, self::PERMISSION);
    }

    public function delete(User $user, TicketType $ticketType): bool
    {
        return $this->canManage($user, $ticketType, self::PERMISSION);
    }
}
