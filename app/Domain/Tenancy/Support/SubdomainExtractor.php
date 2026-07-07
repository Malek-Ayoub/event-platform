<?php

namespace App\Domain\Tenancy\Support;

class SubdomainExtractor
{
    public function extract(?string $host, string $baseDomain): ?string
    {
        if ($host === null || $host === '') {
            return null;
        }

        $host = strtolower($host);
        $baseDomain = strtolower($baseDomain);

        if ($host === $baseDomain) {
            return null;
        }

        $suffix = '.'.$baseDomain;

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        $excluded = config('tenancy.excluded_subdomains', []);

        if (in_array($subdomain, $excluded, true)) {
            return null;
        }

        return $subdomain;
    }
}
