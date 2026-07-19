<?php

namespace App\Services\Venues;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Services\ActivityLogService;
use App\Services\TransactionRunner;
use App\Services\Venues\Data\CreateVenueData;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VenueService
{
    public function __construct(
        private readonly TransactionRunner $transactionRunner,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function createVenue(CreateVenueData $data): Venue
    {
        return $this->transactionRunner->run(function () use ($data): Venue {
            $owner = User::query()->create([
                'name' => $data->ownerName,
                'email' => $data->ownerEmail,
                'password' => Hash::make($data->ownerPassword),
                'is_super_admin' => false,
            ]);

            // Admin-provisioned owners skip self-serve email verification for immediate tenant login.
            $owner->forceFill(['email_verified_at' => now()])->save();

            $venue = Venue::query()->create([
                'name' => $data->name,
                'slug' => $this->uniqueSlugFromName($data->name),
                'subdomain' => Str::lower($data->subdomain),
                'owner_user_id' => $owner->id,
                'commission_rate' => '1.00',
                'status' => 'active',
            ]);

            VenueUser::query()->create([
                'venue_id' => $venue->id,
                'user_id' => $owner->id,
                'role' => 'owner',
            ]);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $venue,
                action: 'created',
                newValues: [
                    'name' => $venue->name,
                    'slug' => $venue->slug,
                    'subdomain' => $venue->subdomain,
                    'status' => $venue->status,
                    'owner_user_id' => $venue->owner_user_id,
                    'commission_rate' => $venue->commission_rate,
                ],
                changedFields: [
                    'name',
                    'slug',
                    'subdomain',
                    'status',
                    'owner_user_id',
                    'commission_rate',
                ],
                ipAddress: $data->ipAddress,
                venueId: (int) $venue->id,
            );

            return $venue->load('owner');
        });
    }

    private function uniqueSlugFromName(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'venue';
        $slug = $base;
        $suffix = 2;

        while (
            Venue::query()
                ->where('slug', $slug)
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
