<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctumTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authenticates_with_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/auth/user');

        $response->assertOk()->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function it_rejects_invalid_bearer_token(): void
    {
        $response = $this->withToken('invalid-token')->getJson('/api/auth/user');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_revokes_token_after_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/user')->assertUnauthorized();
    }

    #[Test]
    public function it_stores_hashed_token_in_database(): void
    {
        $user = User::factory()->create();
        $accessToken = $user->createToken('device');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'device',
        ]);

        $this->assertNotSame($accessToken->plainTextToken, $accessToken->accessToken->token);
    }
}
