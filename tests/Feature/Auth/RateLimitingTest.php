<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');

        // Restore production-strict login limiter for this file only (suite uses a raised testing ceiling).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(
                Str::lower((string) $request->input('email')).'|'.$request->ip()
            );
        });
    }

    #[Test]
    public function it_rate_limits_platform_login_after_five_attempts(): void
    {
        User::factory()->create([
            'email' => 'throttle-platform@example.com',
            'password' => 'Password123!',
        ]);

        $payload = [
            'email' => 'throttle-platform@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', $payload)->assertStatus(429);
    }

    #[Test]
    public function it_rate_limits_tenant_login_after_five_attempts(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $owner->forceFill([
            'email' => 'throttle-tenant@example.com',
            'password' => 'Password123!',
        ])->save();

        $payload = [
            'email' => 'throttle-tenant@example.com',
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->withTenantHost($venue->subdomain)
                ->postJson('/api/tenant/auth/login', $payload)
                ->assertUnauthorized();
        }

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/tenant/auth/login', $payload)
            ->assertStatus(429);
    }

    #[Test]
    public function it_allows_a_legitimate_successful_platform_login(): void
    {
        User::factory()->create([
            'email' => 'legit-login@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'legit-login@example.com',
            'password' => 'Password123!',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'legit-login@example.com',
            'password' => 'Password123!',
            'device_name' => 'second-device',
        ])->assertOk();
    }
}
