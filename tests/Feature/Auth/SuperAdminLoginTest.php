<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuperAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_login_via_platform_api(): void
    {
        $admin = User::factory()->superAdmin()->create([
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.is_super_admin', true)
            ->assertJsonPath('data.user.id', $admin->id);
    }

    #[Test]
    public function super_admin_can_access_current_user_endpoint(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/auth/user');

        $response->assertOk()->assertJsonPath('data.is_super_admin', true);
    }

    #[Test]
    public function regular_user_is_not_super_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.is_super_admin', false);
    }
}
