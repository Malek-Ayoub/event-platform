<?php

namespace Tests\Unit\Policies;

use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new UserPolicy(new TenantContext);
    }

    #[Test]
    public function super_admin_can_view_any_users(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($this->policy->viewAny($admin));
    }

    #[Test]
    public function regular_user_cannot_view_any_users(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[Test]
    public function user_can_view_self(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->view($user, $user));
    }

    #[Test]
    public function user_can_update_self(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->policy->update($user, $user));
    }

    #[Test]
    public function super_admin_can_delete_other_users(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->assertTrue($this->policy->delete($admin, $target));
    }
}
