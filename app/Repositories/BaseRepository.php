<?php

namespace App\Repositories;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    public function __construct(
        protected TenantContextInterface $tenantContext,
    ) {}

    abstract protected function modelClass(): string;

    protected function currentVenueId(): int
    {
        return $this->tenantContext->requireVenueId();
    }

    protected function newQuery()
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass();

        return $modelClass::query();
    }
}
