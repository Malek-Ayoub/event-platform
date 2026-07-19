<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
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

    #[Test]
    public function it_returns_generic_server_error_for_unhandled_exceptions_even_when_debug_is_on(): void
    {
        config()->set('app.debug', true);

        Route::get('/api/__test/unhandled-exception', function (): never {
            throw new RuntimeException('boom — must not appear in the JSON body');
        });

        Log::spy();

        $response = $this->getJson('/api/__test/unhandled-exception');

        $response
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);

        $payload = $response->json();
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('exception', $payload);
        $this->assertArrayNotHasKey('file', $payload);
        $this->assertArrayNotHasKey('line', $payload);
        $this->assertArrayNotHasKey('trace', $payload);
        $this->assertStringNotContainsString('boom', $response->getContent());

        // report() runs independently of our render() fallback.
        Log::shouldHaveReceived('error')->atLeast()->once();
    }
}
