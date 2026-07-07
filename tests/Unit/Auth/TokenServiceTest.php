<?php

namespace Tests\Unit\Auth;

use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private TokenService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TokenService;
    }

    #[Test]
    public function it_creates_sanctum_token(): void
    {
        $user = User::factory()->create();

        $token = $this->service->createToken($user, 'mobile');

        $this->assertNotEmpty($token->plainTextToken);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[Test]
    public function it_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('device');
        $user->withAccessToken($newToken->accessToken);

        $this->service->revokeCurrentToken($user);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_revokes_all_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('one');
        $user->createToken('two');

        $this->service->revokeAllTokens($user);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[Test]
    public function it_uses_configured_default_token_name(): void
    {
        config()->set('platform.auth.token_name', 'default-device');

        $user = User::factory()->create();
        $token = $this->service->createToken($user);

        $this->assertSame('default-device', $token->accessToken->name);
    }
}
