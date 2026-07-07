<?php

namespace App\Policies;

use App\Models\PlatformSetting;
use App\Models\User;

class PlatformSettingPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, PlatformSetting $platformSetting): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, PlatformSetting $platformSetting): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, PlatformSetting $platformSetting): bool
    {
        return $user->isSuperAdmin();
    }
}
