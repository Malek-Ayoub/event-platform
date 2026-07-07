<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_sends_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/auth/password/forgot', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk();
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    #[Test]
    public function it_resets_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker('users')->createToken($user);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    #[Test]
    public function it_rejects_invalid_reset_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/auth/password/reset', [
            'email' => 'reset@example.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_uses_password_reset_tokens_table(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker('users')->createToken($user);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);
    }

    #[Test]
    public function it_validates_forgot_password_email(): void
    {
        $response = $this->postJson('/api/auth/password/forgot', []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_changes_password_for_authenticated_user(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/password/change', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }
}
