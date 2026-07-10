<?php

namespace App\Contracts\Payments\Http;

/**
 * Library-agnostic HTTP client contract for payment gateway integrations.
 *
 * Concrete adapters (e.g. Laravel Http, Guzzle) implement this interface.
 */
interface HttpClientInterface
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    public function post(
        string $url,
        array $payload,
        array $headers,
        int $connectTimeout,
        int $requestTimeout,
        int $retryAttempts,
        int $retryDelayMs,
    ): GatewayHttpResponse;

    /**
     * @param  array<string, string>  $headers
     */
    public function get(
        string $url,
        array $headers,
        int $connectTimeout,
        int $requestTimeout,
        int $retryAttempts,
        int $retryDelayMs,
    ): GatewayHttpResponse;
}
