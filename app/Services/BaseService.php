<?php

namespace App\Services;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;
use App\Domain\Tenancy\Contracts\TenantContextInterface;

abstract class BaseService
{
    public function __construct(
        protected TenantContextInterface $tenantContext,
        protected CorrelationContextInterface $correlationContext,
    ) {}

    protected function currentVenueId(): int
    {
        return $this->tenantContext->requireVenueId();
    }
}
