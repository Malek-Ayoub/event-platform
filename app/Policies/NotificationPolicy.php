<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, Notification $notification): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $notification->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Notification $notification): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $notification->user_id === (int) $user->id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $user->isSuperAdmin();
    }
}
