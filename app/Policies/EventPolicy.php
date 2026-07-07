<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'events.manage';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Event $event): bool
    {
        return $this->canView($user, $event);
    }

    public function create(User $user): bool
    {
        return $this->canCreateInTenant($user, self::PERMISSION);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->canManage($user, $event, self::PERMISSION);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->canManage($user, $event, self::PERMISSION);
    }
}
