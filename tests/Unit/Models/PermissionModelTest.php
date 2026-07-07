<?php

namespace Tests\Unit\Models;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function permission_has_role_and_user_relationships(): void
    {
        $permission = Permission::factory()->create();

        RolePermission::factory()->create(['permission_id' => $permission->id]);
        UserPermission::factory()->create(['permission_id' => $permission->id]);

        $this->assertCount(1, $permission->rolePermissions);
        $this->assertCount(1, $permission->userPermissions()->withoutGlobalScope(BelongsToVenueScope::class)->get());
    }

    #[Test]
    public function user_permission_belongs_to_user_permission_and_venue(): void
    {
        $userPermission = UserPermission::factory()->create();

        $loaded = UserPermission::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->findOrFail($userPermission->id);

        $this->assertNotNull($loaded->user);
        $this->assertNotNull($loaded->permission);
        $this->assertNotNull($loaded->venue);
    }
}
