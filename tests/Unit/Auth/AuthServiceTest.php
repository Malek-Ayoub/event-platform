<?php

namespace Tests\Unit\Auth;

use App\DTOs\Auth\LoginDTO;
use App\DTOs\Auth\RegisterDTO;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegisterService;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AuthService(
            new LoginService,
            new RegisterService,
            new TokenService,
        );
    }

    #[Test]
    public function it_logs_in_and_returns_token_result(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $result = $this->service->login(new LoginDTO(
            email: 'login@example.com',
            password: 'Password123!',
        ));

        $this->assertTrue($result->user->is($user));
        $this->assertNotEmpty($result->plainTextToken);
    }

    #[Test]
    public function it_registers_and_returns_token_result(): void
    {
        $result = $this->service->register(new RegisterDTO(
            name: 'User',
            email: 'register@example.com',
            password: 'Password123!',
        ));

        $this->assertSame('register@example.com', $result->user->email);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function it_logs_out_current_token(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('device');
        $user->withAccessToken($newToken->accessToken);

        $this->service->logout($user);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_logs_out_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('one');
        $user->createToken('two');

        $this->service->logoutAll($user);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
