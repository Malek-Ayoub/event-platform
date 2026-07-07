<?php

namespace App\Domain\Tenancy\Contracts;

use Illuminate\Http\Request;

interface TenantResolverInterface
{
    public function resolve(Request $request): void;
}
