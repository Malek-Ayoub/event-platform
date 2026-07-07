<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_registers_a_new_customer(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Customer One',
            'email' => 'customer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '+963900000001',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'is_super_admin'],
                    'token',
                    'token_type',
                ],
            ])
            ->assertJsonPath('data.user.is_super_admin', false);

        $this->assertDatabaseHas('users', [
            'email' => 'customer@example.com',
            'phone' => '+963900000001',
        ]);
    }

    #[Test]
    public function it_returns_validation_error_for_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Another User',
            'email' => 'taken@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Customer',
            'email' => 'new@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function it_hashes_password_on_registration(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Customer',
            'email' => 'hash@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated();

        $user = User::query()->where('email', 'hash@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('Password123!', $user->password));
    }

    #[Test]
    public function it_issues_sanctum_token_on_registration(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Customer',
            'email' => 'token@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'mobile-app',
        ]);

        $response->assertCreated()->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
