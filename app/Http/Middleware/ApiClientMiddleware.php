<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Resolvers\ApiClientTenantResolver;
use App\Exceptions\InvalidApiClientException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiClientMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(ApiClientTenantResolver::class)->resolve($request);
        } catch (InvalidApiClientException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
