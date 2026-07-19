<?php

namespace App\Exceptions\Handler;

use App\Exceptions\CrossTenantAccessException;
use App\Exceptions\StaleModelException;
use App\Exceptions\TenantNotResolvedException;
use App\Exceptions\Tickets\TicketNotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiExceptionRenderer
{
    public function render(Request $request, Throwable $exception): ?JsonResponse
    {
        if (! $this->shouldRender($request)) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->status);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Unauthenticated.',
            ], 401);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'message' => $exception->getMessage() ?: 'Forbidden.',
            ], 403);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($exception instanceof TicketNotFoundException) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        if ($exception instanceof StaleModelException) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        if ($exception instanceof TenantNotResolvedException || $exception instanceof CrossTenantAccessException) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 403);
        }

        $status = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : $this->domainExceptionStatus($exception);

        if ($status !== null) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }

        // Never fall through to Laravel's debug JSON renderer for API requests —
        // report() still logs the full exception independently of this response.
        return response()->json([
            'message' => 'Server Error',
        ], 500);
    }

    private function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function domainExceptionStatus(Throwable $exception): ?int
    {
        $class = $exception::class;

        if (! str_starts_with($class, 'App\\Exceptions\\')) {
            return null;
        }

        if (str_starts_with($class, 'App\\Exceptions\\Auth\\')) {
            return 401;
        }

        if (
            str_starts_with($class, 'App\\Exceptions\\Orders\\')
            || str_starts_with($class, 'App\\Exceptions\\Payments\\')
            || str_starts_with($class, 'App\\Exceptions\\Refunds\\')
            || str_starts_with($class, 'App\\Exceptions\\Commissions\\')
            || str_starts_with($class, 'App\\Exceptions\\Settlements\\')
            || str_starts_with($class, 'App\\Exceptions\\Events\\')
            || str_starts_with($class, 'App\\Exceptions\\Tickets\\')
            || str_starts_with($class, 'App\\Exceptions\\Venues\\')
            || str_starts_with($class, 'App\\Exceptions\\PlatformSettings\\')
        ) {
            return 422;
        }

        return null;
    }
}
