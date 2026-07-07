<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookLog;

class WebhookLogPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, WebhookLog $webhookLog): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, WebhookLog $webhookLog): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, WebhookLog $webhookLog): bool
    {
        return $user->isSuperAdmin();
    }
}
