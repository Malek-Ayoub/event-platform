<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Resolvers\SubdomainTenantResolver;
use App\Exceptions\TenantNotResolvedException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(SubdomainTenantResolver::class)->resolve($request);
        } catch (TenantNotResolvedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }

        return $next($request);
    }
}
