<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Root OpenAPI document metadata (Phase 6.8 — §6.8).
 *
 * This class carries no runtime behaviour; it only anchors the top-level
 * `#[OA\Info]` / `#[OA\Server]` attributes that zircote/swagger-php needs
 * somewhere in the scanned `app/` tree to build the document envelope.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Event Platform API',
    description: <<<'DESC'
        Multi-tenant event platform API.

        - **Global routes** (`/api/...`) — authentication and platform (super admin) endpoints.
        - **Tenant routes** (`/api/tenant/...`) — resolved via the venue subdomain; every endpoint
          below this prefix is scoped to the authenticated user's venue.

        All mutating endpoints follow the `FormRequest → DTO → Service → ApiResource → ApiResponse`
        pipeline. **OpenAPI schemas in `app/OpenApi/` are a read-only projection** of that contract —
        FormRequest + ApiResource + DTO remain the source of truth; update both in the same commit.
        DESC,
)]
#[OA\Server(url: '/api', description: 'Global platform API')]
#[OA\Server(url: '/api/tenant', description: 'Tenant-scoped API (requires venue subdomain resolution)')]
#[OA\Tag(name: 'Auth', description: 'Registration, login, tokens, password reset, email verification')]
#[OA\Tag(name: 'Events', description: 'Events, categories, and ticket types (tenant-scoped)')]
#[OA\Tag(name: 'Commerce', description: 'Products, product variants, coupons, and promo codes (tenant-scoped)')]
#[OA\Tag(name: 'Orders', description: 'Order creation and retrieval (tenant-scoped)')]
#[OA\Tag(name: 'Payments', description: 'Payment transaction lifecycle (tenant-scoped)')]
#[OA\Tag(name: 'Platform', description: 'Tax rates (tenant-scoped) and platform settings (global, super admin)')]
class OpenApiSpec {}
