<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogPolicy extends TenantResourcePolicy
{
    private const PERMISSION = 'reports.view';

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $this->tenantContext->isResolved();
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $venueId = $this->venueIdFrom($activityLog);

        if ($venueId === null || ! $user->belongsToVenue($venueId)) {
            return false;
        }

        return $this->permissionService->can($user, self::PERMISSION, $venueId);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }
}
