<?php

namespace App\Services\Authorization;

use App\Events\Permissions\PermissionGranted;
use App\Events\Permissions\PermissionRevoked;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\User;
use App\Models\UserPermission;
use App\Policies\UserPermissionPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /** @var array<string, list<string>> */
    private array $rolePermissionSlugsCache = [];

    public function can(User $user, string $permissionSlug, ?int $venueId = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($venueId === null) {
            return false;
        }

        $membership = $user->venueMembership($venueId);

        if ($membership === null) {
            return false;
        }

        if ($this->hasUserPermission($user->id, $venueId, $permissionSlug)) {
            return true;
        }

        return $this->hasRolePermission($membership->role, $permissionSlug);
    }

    public function grant(User $actor, User $target, Permission $permission, int $venueId): UserPermission
    {
        $this->authorizeManagePermissions($actor, $target, $venueId);

        return DB::transaction(function () use ($actor, $target, $permission, $venueId): UserPermission {
            $userPermission = $this->userPermissionQuery()->firstOrCreate([
                'venue_id' => $venueId,
                'user_id' => $target->id,
                'permission_id' => $permission->id,
            ]);

            PermissionGranted::dispatch($actor, $target, $permission, $venueId);

            return $userPermission;
        });
    }

    public function revoke(User $actor, User $target, Permission $permission, int $venueId): void
    {
        $this->authorizeManagePermissions($actor, $target, $venueId);

        DB::transaction(function () use ($actor, $target, $permission, $venueId): void {
            $deleted = $this->userPermissionQuery()
                ->where('venue_id', $venueId)
                ->where('user_id', $target->id)
                ->where('permission_id', $permission->id)
                ->delete();

            if ($deleted > 0) {
                PermissionRevoked::dispatch($actor, $target, $permission, $venueId);
            }
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function sync(User $actor, User $target, array $permissionIds, int $venueId): void
    {
        $this->authorizeManagePermissions($actor, $target, $venueId);

        DB::transaction(function () use ($actor, $target, $permissionIds, $venueId): void {
            $existing = $this->userPermissionQuery()
                ->where('venue_id', $venueId)
                ->where('user_id', $target->id)
                ->get()
                ->keyBy('permission_id');

            $desired = collect($permissionIds)->unique()->values();

            foreach ($desired as $permissionId) {
                if (! $existing->has($permissionId)) {
                    $permission = Permission::query()->findOrFail($permissionId);
                    $this->grant($actor, $target, $permission, $venueId);
                }
            }

            foreach ($existing as $permissionId => $userPermission) {
                if (! $desired->contains($permissionId)) {
                    $this->revoke($actor, $target, $userPermission->permission, $venueId);
                }
            }
        });
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissionsFor(User $user, int $venueId): Collection
    {
        if ($user->isSuperAdmin()) {
            return Permission::query()->orderBy('slug')->get();
        }

        $membership = $user->venueMembership($venueId);

        if ($membership === null) {
            return collect();
        }

        $slugs = $this->permissionSlugsForRole($membership->role);

        $grantedSlugs = $this->userPermissionQuery()
            ->where('venue_id', $venueId)
            ->where('user_id', $user->id)
            ->with('permission')
            ->get()
            ->map(fn (UserPermission $userPermission): string => $userPermission->permission->slug);

        $allSlugs = $slugs->merge($grantedSlugs)->unique()->values();

        return Permission::query()
            ->whereIn('slug', $allSlugs)
            ->orderBy('slug')
            ->get();
    }

    public function isVenueOwner(User $user, int $venueId): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $membership = $user->venueMembership($venueId);

        return $membership !== null && $membership->isOwner();
    }

    public function isVenueStaff(User $user, int $venueId): bool
    {
        $membership = $user->venueMembership($venueId);

        return $membership !== null && $membership->isStaff();
    }

    protected function authorizeManagePermissions(User $actor, User $target, int $venueId): void
    {
        if (! app(UserPermissionPolicy::class)->manageUserPermissions($actor, $target, $venueId)) {
            throw new AuthorizationException('You are not allowed to manage permissions for this user.');
        }
    }

    protected function hasRolePermission(string $role, string $permissionSlug): bool
    {
        return in_array($permissionSlug, $this->permissionSlugsForRole($role)->all(), true);
    }

    protected function hasUserPermission(int $userId, int $venueId, string $permissionSlug): bool
    {
        return $this->userPermissionQuery()
            ->where('venue_id', $venueId)
            ->where('user_id', $userId)
            ->whereHas('permission', fn ($query) => $query->where('slug', $permissionSlug))
            ->exists();
    }

    /**
     * @return Collection<int, string>
     */
    protected function permissionSlugsForRole(string $role): Collection
    {
        if (! isset($this->rolePermissionSlugsCache[$role])) {
            $this->rolePermissionSlugsCache[$role] = RolePermission::query()
                ->where('role', $role)
                ->with('permission')
                ->get()
                ->map(fn (RolePermission $rolePermission): string => $rolePermission->permission->slug)
                ->values()
                ->all();
        }

        return collect($this->rolePermissionSlugsCache[$role]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<UserPermission>
     */
    protected function userPermissionQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return UserPermission::query()->withoutGlobalScope(BelongsToVenueScope::class);
    }
}
