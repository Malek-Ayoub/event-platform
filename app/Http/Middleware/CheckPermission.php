<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Contracts\TenantContextInterface;
use App\Models\User;
use App\Services\Authorization\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function __construct(
        private readonly PermissionService $permissionService,
        private readonly TenantContextInterface $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        $venueId = $this->tenantContext->getVenueId();

        if (! $this->permissionService->can($user, $permission, $venueId)) {
            abort(Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
