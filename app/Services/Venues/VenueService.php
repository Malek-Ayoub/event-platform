<?php

namespace App\Services\Venues;

use App\Exceptions\Venues\InvalidVenueStateTransitionException;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Services\ActivityLogService;
use App\Services\TransactionRunner;
use App\Services\Venues\Data\CreateVenueData;
use App\Services\Venues\Data\UpdateVenueData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    /**
     * @return LengthAwarePaginator<int, Venue>
     */
    public function listVenues(int $perPage = 15): LengthAwarePaginator
    {
        return Venue::query()
            ->with('owner')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function getVenue(Venue $venue): Venue
    {
        return $venue->loadMissing('owner');
    }

    public function updateVenue(Venue $venue, UpdateVenueData $data): Venue
    {
        return $this->transactionRunner->run(function () use ($venue, $data): Venue {
            $locked = Venue::query()->whereKey($venue->id)->lockForUpdate()->firstOrFail();
            $oldValues = $this->venueSnapshot($locked);
            $attributes = [];
            $changedFields = [];

            if ($data->name !== null) {
                $attributes['name'] = $data->name;
                $changedFields[] = 'name';
            }

            if ($data->commissionRate !== null) {
                $attributes['commission_rate'] = $data->commissionRate;
                $changedFields[] = 'commission_rate';
            }

            if ($changedFields === []) {
                return $locked->loadMissing('owner');
            }

            $locked->fill($attributes)->save();
            $locked = $locked->fresh(['owner']);

            $this->activityLogService->record(
                actor: $data->actor,
                entity: $locked,
                action: 'updated',
                oldValues: $oldValues,
                newValues: $this->venueSnapshot($locked),
                changedFields: $changedFields,
                ipAddress: $data->ipAddress,
                venueId: (int) $locked->id,
            );

            return $locked;
        });
    }

    public function suspendVenue(Venue $venue, User $actor, ?string $ipAddress = null): Venue
    {
        return $this->transactionRunner->run(function () use ($venue, $actor, $ipAddress): Venue {
            $locked = Venue::query()->whereKey($venue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                throw InvalidVenueStateTransitionException::cannotSuspendFrom((string) $locked->status);
            }

            $oldValues = $this->venueSnapshot($locked);
            $locked->fill(['status' => 'suspended'])->save();
            $locked = $locked->fresh(['owner']);

            $this->activityLogService->record(
                actor: $actor,
                entity: $locked,
                action: 'suspended',
                oldValues: $oldValues,
                newValues: $this->venueSnapshot($locked),
                changedFields: ['status'],
                ipAddress: $ipAddress,
                venueId: (int) $locked->id,
            );

            return $locked;
        });
    }

    public function activateVenue(Venue $venue, User $actor, ?string $ipAddress = null): Venue
    {
        return $this->transactionRunner->run(function () use ($venue, $actor, $ipAddress): Venue {
            $locked = Venue::query()->whereKey($venue->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'suspended') {
                throw InvalidVenueStateTransitionException::cannotActivateFrom((string) $locked->status);
            }

            $oldValues = $this->venueSnapshot($locked);
            $locked->fill(['status' => 'active'])->save();
            $locked = $locked->fresh(['owner']);

            $this->activityLogService->record(
                actor: $actor,
                entity: $locked,
                action: 'activated',
                oldValues: $oldValues,
                newValues: $this->venueSnapshot($locked),
                changedFields: ['status'],
                ipAddress: $ipAddress,
                venueId: (int) $locked->id,
            );

            return $locked;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function venueSnapshot(Venue $venue): array
    {
        return [
            'name' => $venue->name,
            'slug' => $venue->slug,
            'subdomain' => $venue->subdomain,
            'status' => $venue->status,
            'owner_user_id' => $venue->owner_user_id,
            'commission_rate' => $venue->commission_rate,
        ];
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
