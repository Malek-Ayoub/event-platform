<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class UserPolicy extends BasePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() || $actor->is($target);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() || $actor->is($target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin() && ! $actor->is($target);
    }
}
