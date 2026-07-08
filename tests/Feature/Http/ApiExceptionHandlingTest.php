<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_json_validation_errors_for_api_routes(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['email', 'password']]);
    }

    #[Test]
    public function it_returns_json_authentication_errors_for_api_routes(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function it_returns_json_for_unauthenticated_protected_routes(): void
    {
        $response = $this->getJson('/api/auth/user');

        $response
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function it_keeps_existing_auth_success_payload_shape(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'Password123!',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'token_type', 'user']]);
    }
}
