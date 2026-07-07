<?php

namespace App\Support\Concerns;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Exceptions\CrossTenantAccessException;
use App\Exceptions\TenantNotResolvedException;
use App\Models\Scopes\BelongsToVenueScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToVenue
{
    public static function bootBelongsToVenue(): void
    {
        static::addGlobalScope(new BelongsToVenueScope);

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('venue_id')) {
                $tenantContext = app(TenantContextInterface::class);

                if (! $tenantContext->isResolved()) {
                    throw new TenantNotResolvedException('Cannot create tenant-scoped model without resolved tenant context.');
                }

                $model->setAttribute('venue_id', $tenantContext->requireVenueId());
            }
        });
    }

    public function ensureSameTenant(?int $venueId = null): void
    {
        $expectedVenueId = $venueId ?? app(TenantContextInterface::class)->requireVenueId();

        if ((int) $this->getAttribute('venue_id') !== (int) $expectedVenueId) {
            throw new CrossTenantAccessException;
        }
    }

    public function scopeForVenue($query, int $venueId)
    {
        return $query->withoutGlobalScope(BelongsToVenueScope::class)
            ->where($this->qualifyColumn('venue_id'), $venueId);
    }
}
