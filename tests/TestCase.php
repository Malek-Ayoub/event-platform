<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;
use Tests\Support\Concerns\BindsTenantContext;
use Tests\Support\Concerns\CreatesAuthFixtures;
use Tests\Support\Concerns\SeedsPermissions;

abstract class TestCase extends BaseTestCase
{
    use BindsTenantContext, CreatesAuthFixtures, SeedsPermissions;

    protected function withTenantHost(string $subdomain): static
    {
        URL::forceRootUrl(sprintf(
            'http://%s.%s',
            $subdomain,
            config('tenancy.base_domain', 'localhost'),
        ));

        return $this;
    }
}
