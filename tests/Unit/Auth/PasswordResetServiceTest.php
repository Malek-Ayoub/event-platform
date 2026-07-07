<?php

namespace Tests\Unit\Auth;

use App\DTOs\Auth\ChangePasswordDTO;
use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PasswordResetService;
    }

    #[Test]
    public function it_changes_password_with_valid_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);

        $this->service->changePassword($user, new ChangePasswordDTO(
            currentPassword: 'OldPassword123!',
            password: 'NewPassword123!',
        ));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }

    #[Test]
    public function it_rejects_invalid_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword123!']);

        $this->expectException(ValidationException::class);

        $this->service->changePassword($user, new ChangePasswordDTO(
            currentPassword: 'wrong',
            password: 'NewPassword123!',
        ));
    }

    #[Test]
    public function it_resets_password_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker('users')->createToken($user);

        $this->service->resetPassword(new \App\DTOs\Auth\ResetPasswordDTO(
            email: 'reset@example.com',
            token: $token,
            password: 'NewPassword123!',
        ));

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
    }
}
