<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthExceptionMappingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invalid_login_credentials_return_401(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonStructure(['message']);

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Password123!',
        ])->assertUnauthorized()->assertJsonStructure(['message']);
    }

    #[Test]
    public function login_validation_errors_return_422(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    #[Test]
    public function register_validation_errors_return_422(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function invalid_bearer_token_returns_401(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/auth/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function unauthenticated_protected_routes_return_401(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
        $this->getJson('/api/auth/user')->assertUnauthorized();
        $this->postJson('/api/auth/password/change', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnauthorized();
    }

    #[Test]
    public function invalid_email_verification_hash_returns_403(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1('wrong@example.com'),
            ],
        );

        $this->actingAs($user, 'sanctum')
            ->getJson($url)
            ->assertForbidden();
    }

    #[Test]
    public function invalid_password_reset_token_returns_422(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'reset@example.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function forgot_password_validation_returns_422(): void
    {
        $this->postJson('/api/auth/password/forgot', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
