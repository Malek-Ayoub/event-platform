<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_out_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/auth/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_requires_authentication_for_logout(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_revokes_all_tokens_on_logout_all(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('one')->plainTextToken;
        $user->createToken('two');

        $response = $this->withToken($token)->postJson('/api/auth/logout-all');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_returns_current_user(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);
        $token = $user->createToken('device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/user');

        $response
            ->assertOk()
            ->assertJsonPath('data.email', 'me@example.com');
    }
}
