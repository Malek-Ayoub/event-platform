<?php

namespace App\Services\Payments\Gateway\Http;

use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

final class PaymentGatewayHttpClient
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(GatewayProviderConfig $config, string $path, array $payload): Response
    {
        $url = rtrim($config->baseUrl, '/').'/'.ltrim($path, '/');

        $pending = $this->http
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => 'Bearer '.$config->apiKey,
            ])
            ->connectTimeout($config->connectTimeout)
            ->timeout($config->requestTimeout);

        if ($config->retryAttempts > 0) {
            $pending = $pending->retry(
                times: $config->retryAttempts,
                sleepMilliseconds: $config->retryDelayMs,
                when: static fn ($exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
        }

        return $pending->post($url, $payload);
    }
}
