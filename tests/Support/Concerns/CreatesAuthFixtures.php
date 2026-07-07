<?php

namespace Tests\Support\Concerns;

use App\Models\ApiClient;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Database\Factories\ApiClientFactory;
use Illuminate\Support\Facades\Hash;

trait CreatesAuthFixtures
{
    /**
     * @return array{user: User, venue: Venue}
     */
    protected function createVenueOwner(): array
    {
        $user = User::factory()->create();
        $venue = Venue::factory()->create([
            'owner_user_id' => $user->id,
        ]);

        VenueUser::query()->create([
            'venue_id' => $venue->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        return ['user' => $user, 'venue' => $venue];
    }

    /**
     * @return array{user: User, venue: Venue}
     */
    protected function createVenueStaff(Venue $venue): array
    {
        $user = User::factory()->create();

        VenueUser::query()->create([
            'venue_id' => $venue->id,
            'user_id' => $user->id,
            'role' => 'staff',
        ]);

        return ['user' => $user, 'venue' => $venue];
    }

    /**
     * @return array{client: ApiClient, plainSecret: string}
     */
    protected function createApiClient(Venue $venue, ?string $plainSecret = null): array
    {
        $plainSecret ??= ApiClientFactory::$plainSecret;

        $client = ApiClient::query()->create([
            'venue_id' => $venue->id,
            'name' => 'Partner',
            'api_key' => 'partner-test-key',
            'secret' => Hash::make($plainSecret),
            'scopes' => ['events.read'],
            'active' => true,
        ]);

        return ['client' => $client, 'plainSecret' => $plainSecret];
    }
}
