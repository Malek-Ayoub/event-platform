<?php

namespace App\Policies;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Eloquent\Model;

abstract class TenantResourcePolicy extends BasePolicy
{
    public function __construct(
        TenantContextInterface $tenantContext,
        protected PermissionService $permissionService,
    ) {
        parent::__construct($tenantContext);
    }

    protected function canView(User $user, Model $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $venueId = $this->venueIdFrom($model);

        return $venueId !== null && $user->belongsToVenue($venueId);
    }

    protected function canManage(User $user, Model $model, string $permissionSlug): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $venueId = $this->venueIdFrom($model);

        if ($venueId === null || ! $user->belongsToVenue($venueId)) {
            return false;
        }

        return $this->permissionService->can($user, $permissionSlug, $venueId);
    }

    protected function canCreateInTenant(User $user, string $permissionSlug): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->tenantContext->isResolved()) {
            return false;
        }

        $venueId = $this->tenantContext->requireVenueId();

        if (! $user->belongsToVenue($venueId)) {
            return false;
        }

        return $this->permissionService->can($user, $permissionSlug, $venueId);
    }

    protected function venueIdFrom(Model $model): ?int
    {
        $venueId = $model->getAttribute('venue_id');

        return $venueId !== null ? (int) $venueId : null;
    }
}
