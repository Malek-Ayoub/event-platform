<?php

namespace Tests\Support\Concerns;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Domain\Tenancy\TenantContext;

trait BindsTenantContext
{
    protected function bindTenant(int $venueId): TenantContext
    {
        $context = new TenantContext;
        $context->bind($venueId, 'test');
        $this->app->instance(TenantContextInterface::class, $context);

        return $context;
    }
}
