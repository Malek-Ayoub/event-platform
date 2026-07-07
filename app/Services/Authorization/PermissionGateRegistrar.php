<?php

namespace App\Services\Authorization;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class PermissionGateRegistrar
{
    public function registerAll(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Permission::query()->pluck('slug')->each(function (string $slug): void {
            if (Gate::has($slug)) {
                return;
            }

            Gate::define($slug, function (User $user, ?int $venueId = null) use ($slug): bool {
                return app(PermissionService::class)->can($user, $slug, $venueId);
            });
        });
    }
}
