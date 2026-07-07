<?php

namespace App\Policies;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Exceptions\CrossTenantAccessException;
use App\Exceptions\TenantNotResolvedException;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    public function __construct(
        protected TenantContextInterface $tenantContext,
    ) {}

    protected function sameTenant(Model $model): bool
    {
        if (! $this->tenantContext->isResolved()) {
            throw new TenantNotResolvedException;
        }

        if (! $model->getAttribute('venue_id')) {
            return false;
        }

        return (int) $model->getAttribute('venue_id') === $this->tenantContext->requireVenueId();
    }

    protected function authorizeSameTenant(Model $model): void
    {
        if (! $this->sameTenant($model)) {
            throw new CrossTenantAccessException;
        }
    }
}
