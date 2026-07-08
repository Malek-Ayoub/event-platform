<?php

namespace App\OpenApi;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Events\CreateEventRequest;
use App\Http\Requests\Orders\CreateOrderRequest;
use App\Http\Requests\Payments\InitiatePaymentRequest;
use App\Http\Resources\Auth\ApiTokenResource;
use App\Http\Resources\Events\EventResource;
use App\Http\Resources\Orders\OrderResource;
use App\Http\Resources\Payments\PaymentTransactionResource;

/**
 * Maps named API routes to their contract source-of-truth classes.
 *
 * OpenAPI schemas in `app/OpenApi/Schemas/` are **projections** of these classes —
 * they must be updated in the same commit when the contract changes.
 *
 * @see IMPLEMENTATION_ROADMAP.md §6.8 — OpenAPI Contract Sync Checklist
 */
final class OpenApiContractRegistry
{
    /**
     * Core routes whose request/response projections must always exist in the spec.
     *
     * Keys are Laravel route names; values name the FormRequest / ApiResource
     * classes whose fields the OpenAPI schema must mirror.
     *
     * @return array<string, array{request?: class-string, resource?: class-string}>
     */
    public static function coreProjections(): array
    {
        return [
            'auth.login' => [
                'request' => LoginRequest::class,
                'resource' => ApiTokenResource::class,
            ],
            'auth.register' => [
                'request' => RegisterRequest::class,
                'resource' => ApiTokenResource::class,
            ],
            'tenant.events.store' => [
                'request' => CreateEventRequest::class,
                'resource' => EventResource::class,
            ],
            'tenant.orders.store' => [
                'request' => CreateOrderRequest::class,
                'resource' => OrderResource::class,
            ],
            'tenant.payments.store' => [
                'request' => InitiatePaymentRequest::class,
                'resource' => PaymentTransactionResource::class,
            ],
        ];
    }

    /**
     * OpenAPI component schema name for a FormRequest or ApiResource class.
     *
     * @param  class-string  $class
     */
    public static function schemaNameFor(string $class): string
    {
        return class_basename($class);
    }

    /**
     * Route files whose named routes must appear in the OpenAPI spec.
     *
     * @return list<string>
     */
    public static function documentedRouteFiles(): array
    {
        return [
            base_path('routes/api.php'),
            base_path('routes/tenant.php'),
        ];
    }
}
