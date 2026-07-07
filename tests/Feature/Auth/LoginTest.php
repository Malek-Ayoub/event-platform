<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_logs_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }

    #[Test]
    public function it_rejects_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_rejects_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function it_creates_personal_access_token_on_login(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
            'device_name' => 'web',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function it_allows_multiple_tokens_for_same_user(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
            'device_name' => 'web',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
            'device_name' => 'mobile',
        ])->assertOk();

        $this->assertSame(2, $user->tokens()->count());
    }
}
