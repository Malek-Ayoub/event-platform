<?php

namespace App\Models\Scopes;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Exceptions\TenantNotResolvedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BelongsToVenueScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! $this->shouldApplyScope($model)) {
            return;
        }

        $tenantContext = app(TenantContextInterface::class);

        if (! $tenantContext->isResolved()) {
            throw new TenantNotResolvedException('Tenant context must be resolved before querying tenant-scoped models.');
        }

        $builder->where(
            $model->qualifyColumn('venue_id'),
            $tenantContext->requireVenueId(),
        );
    }

    protected function shouldApplyScope(Model $model): bool
    {
        if (! property_exists($model, 'tenantScoped') && ! method_exists($model, 'isTenantScoped')) {
            return true;
        }

        if (method_exists($model, 'isTenantScoped')) {
            return $model->isTenantScoped();
        }

        return (bool) $model->tenantScoped;
    }
}
