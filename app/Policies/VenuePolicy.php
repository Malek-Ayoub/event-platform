<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;

class VenuePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Venue $venue): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToVenue((int) $venue->getKey());
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Venue $venue): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membership = $user->venueMembership((int) $venue->getKey());

        return $membership !== null && $membership->isOwner();
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->isSuperAdmin();
    }
}
