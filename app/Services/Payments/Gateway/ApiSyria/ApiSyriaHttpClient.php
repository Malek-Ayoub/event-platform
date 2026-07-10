<?php

namespace App\Services\Payments\Gateway\ApiSyria;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\Contracts\Payments\Http\HttpClientInterface;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;

/**
 * Phase 7.10 — HTTP client for API Syria read endpoints (GET + X-Api-Key).
 *
 * @see https://apisyria.com/api/docs
 */
final class ApiSyriaHttpClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function get(GatewayProviderConfig $config, array $query): GatewayHttpResponse
    {
        $url = rtrim($config->baseUrl, '/').'?'.http_build_query($query);

        return $this->httpClient->get(
            url: $url,
            headers: [
                'X-Api-Key' => $config->apiKey,
                'Accept' => 'application/json',
            ],
            connectTimeout: $config->connectTimeout,
            requestTimeout: $config->requestTimeout,
            retryAttempts: $config->retryAttempts,
            retryDelayMs: $config->retryDelayMs,
        );
    }
}
