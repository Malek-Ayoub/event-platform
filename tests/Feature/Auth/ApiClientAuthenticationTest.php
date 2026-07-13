<?php

namespace Tests\Feature\Auth;

use App\Models\ApiClient;
use Database\Factories\ApiClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiClientAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_authenticates_valid_api_client_credentials(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['plainSecret' => $secret] = $this->createApiClient($venue);

        $response = $this
            ->withHeaders([
                'X-Api-Key' => 'partner-test-key',
                'X-Api-Secret' => $secret,
            ])
            ->getJson('/api/partner/ping');

        $response
            ->assertOk()
            ->assertJsonPath('venue_id', $venue->id)
            ->assertJsonPath('source', 'api_client');
    }

    #[Test]
    public function it_rejects_invalid_api_client_secret(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->createApiClient($venue);

        $response = $this
            ->withHeaders([
                'X-Api-Key' => 'partner-test-key',
                'X-Api-Secret' => 'wrong-secret',
            ])
            ->getJson('/api/partner/ping');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_rejects_inactive_api_client(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['plainSecret' => $secret] = $this->createApiClient($venue);

        DB::table('api_clients')
            ->where('api_key', 'partner-test-key')
            ->update(['active' => false]);

        $response = $this
            ->withHeaders([
                'X-Api-Key' => 'partner-test-key',
                'X-Api-Secret' => $secret,
            ])
            ->getJson('/api/partner/ping');

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_updates_last_used_at_for_api_client(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['client' => $client, 'plainSecret' => $secret] = $this->createApiClient($venue);

        $this->assertNull($client->fresh()->last_used_at);

        $this
            ->withHeaders([
                'X-Api-Key' => 'partner-test-key',
                'X-Api-Secret' => $secret,
            ])
            ->getJson('/api/partner/ping')
            ->assertOk();

        $this->assertNotNull($client->fresh()->last_used_at);
    }

    #[Test]
    public function factory_api_client_works_with_default_secret(): void
    {
        $client = ApiClient::factory()->create();

        $response = $this
            ->withHeaders([
                'X-Api-Key' => $client->api_key,
                'X-Api-Secret' => ApiClientFactory::$plainSecret,
            ])
            ->getJson('/api/partner/ping');

        $response->assertOk()->assertJsonPath('venue_id', $client->venue_id);
    }
}
