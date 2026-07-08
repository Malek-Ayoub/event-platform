<?php

namespace Tests\Feature\PlatformSettings;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformSettingApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_view_and_update_platform_settings(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        PlatformSetting::factory()->create([
            'commission_rate' => 1.00,
            'settings' => ['default_currency' => 'USD'],
            'version' => 1,
        ]);

        $this->withToken($token)->getJson('/api/platform/settings')
            ->assertOk()
            ->assertJsonPath('data.commission_rate', '1.00')
            ->assertJsonPath('data.settings.default_currency', 'USD');

        $this->withToken($token)->putJson('/api/platform/settings', [
            'version' => 1,
            'commission_rate' => '2.50',
            'settings' => ['support_email' => 'support@example.com'],
        ])
            ->assertOk()
            ->assertJsonPath('data.commission_rate', '2.50')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.settings.support_email', 'support@example.com');
    }

    #[Test]
    public function venue_owner_cannot_access_platform_settings(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        PlatformSetting::factory()->create();

        $this->withToken($token)->getJson('/api/platform/settings')->assertForbidden();
        $this->withToken($token)->putJson('/api/platform/settings', [
            'version' => 1,
            'commission_rate' => '3.00',
        ])->assertForbidden();
    }

    #[Test]
    public function update_returns_conflict_on_stale_version(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        PlatformSetting::factory()->create(['version' => 2]);

        $this->withToken($token)->putJson('/api/platform/settings', [
            'version' => 1,
            'commission_rate' => '3.00',
        ])->assertStatus(409);
    }
}
