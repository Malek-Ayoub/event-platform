<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPermission;

class UserPermissionPolicy extends BasePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin() || $this->isOwnerInResolvedTenant($actor);
    }

    public function view(User $actor, UserPermission $userPermission): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $this->sameTenant($userPermission) && $this->isOwnerInResolvedTenant($actor);
    }

    public function create(User $actor): bool
    {
        return $this->canManagePermissions($actor);
    }

    public function update(User $actor, UserPermission $userPermission): bool
    {
        return $this->canManageUserPermission($actor, $userPermission);
    }

    public function delete(User $actor, UserPermission $userPermission): bool
    {
        return $this->canManageUserPermission($actor, $userPermission);
    }

    /**
     * Gate helper: may actor grant/revoke permissions for target at venue?
     */
    public function manageUserPermissions(User $actor, User $target, int $venueId): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        $actorMembership = $actor->venueMembership($venueId);

        if ($actorMembership === null || ! $actorMembership->isOwner()) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        $targetMembership = $target->venueMembership($venueId);

        return $targetMembership !== null && $targetMembership->isStaff();
    }

    protected function canManagePermissions(User $actor): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $this->isOwnerInResolvedTenant($actor);
    }

    protected function canManageUserPermission(User $actor, UserPermission $userPermission): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if (! $this->sameTenant($userPermission)) {
            return false;
        }

        return $this->isOwnerInResolvedTenant($actor);
    }

    protected function isOwnerInResolvedTenant(User $actor): bool
    {
        if (! $this->tenantContext->isResolved()) {
            return false;
        }

        $membership = $actor->venueMembership($this->tenantContext->requireVenueId());

        return $membership !== null && $membership->isOwner();
    }
}
