<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant Resolution
    |--------------------------------------------------------------------------
    |
    | Locked architecture (v1.0 §2, v1.3 §53): tenant context is bound per
    | request via TenantMiddleware (subdomain) or ApiClientMiddleware (API key).
    | Both produce the same TenantContext shape.
    |
    */

    'base_domain' => env('TENANCY_BASE_DOMAIN', 'localhost'),

    'excluded_subdomains' => ['www', 'api', 'admin'],

    'subdomain_cache_ttl' => (int) env('TENANCY_SUBDOMAIN_CACHE_TTL', 3600),

    'api_client_cache_ttl' => (int) env('TENANCY_API_CLIENT_CACHE_TTL', 3600),

    'cache_prefix' => env('TENANCY_CACHE_PREFIX', 'tenant'),

    'headers' => [
        'api_key' => env('TENANCY_API_KEY_HEADER', 'X-Api-Key'),
        'api_secret' => env('TENANCY_API_SECRET_HEADER', 'X-Api-Secret'),
    ],

];
