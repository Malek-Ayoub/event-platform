<?php

namespace App\Services\Payments\Gateway\Http;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\Contracts\Payments\Http\HttpClientInterface;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;

final class PaymentGatewayHttpClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(GatewayProviderConfig $config, string $path, array $payload): GatewayHttpResponse
    {
        $url = rtrim($config->baseUrl, '/').'/'.ltrim($path, '/');

        return $this->httpClient->post(
            url: $url,
            payload: $payload,
            headers: [
                'Authorization' => 'Bearer '.$config->apiKey,
            ],
            connectTimeout: $config->connectTimeout,
            requestTimeout: $config->requestTimeout,
            retryAttempts: $config->retryAttempts,
            retryDelayMs: $config->retryDelayMs,
        );
    }
}
