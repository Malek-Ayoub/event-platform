<?php

namespace App\Services\Payments\Gateway\Http\Adapters;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\Contracts\Payments\Http\HttpClientInterface;
use App\Enums\Payments\GatewayOutcome;
use App\Exceptions\Payments\Gateway\GatewayTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

final class LaravelHttpClientAdapter implements HttpClientInterface
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    public function post(
        string $url,
        array $payload,
        array $headers,
        int $connectTimeout,
        int $requestTimeout,
        int $retryAttempts,
        int $retryDelayMs,
    ): GatewayHttpResponse {
        return $this->send(
            method: 'post',
            url: $url,
            payload: $payload,
            headers: $headers,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            retryAttempts: $retryAttempts,
            retryDelayMs: $retryDelayMs,
        );
    }

    public function get(
        string $url,
        array $headers,
        int $connectTimeout,
        int $requestTimeout,
        int $retryAttempts,
        int $retryDelayMs,
    ): GatewayHttpResponse {
        return $this->send(
            method: 'get',
            url: $url,
            payload: [],
            headers: $headers,
            connectTimeout: $connectTimeout,
            requestTimeout: $requestTimeout,
            retryAttempts: $retryAttempts,
            retryDelayMs: $retryDelayMs,
        );
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $payload
     */
    private function send(
        string $method,
        string $url,
        array $payload,
        array $headers,
        int $connectTimeout,
        int $requestTimeout,
        int $retryAttempts,
        int $retryDelayMs,
    ): GatewayHttpResponse {
        try {
            $pending = $this->http
                ->acceptJson()
                ->withHeaders($headers)
                ->connectTimeout($connectTimeout)
                ->timeout($requestTimeout);

            if ($method === 'post') {
                $pending = $pending->asJson();
            }

            if ($retryAttempts > 0) {
                $pending = $pending->retry(
                    times: $retryAttempts,
                    sleepMilliseconds: $retryDelayMs,
                    when: static fn ($exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                );
            }

            $response = $method === 'post'
                ? $pending->post($url, $payload)
                : $pending->get($url);

            $body = $response->json();

            return new GatewayHttpResponse(
                status: $response->status(),
                body: is_array($body) ? $body : null,
                rawBody: $response->body(),
            );
        } catch (Throwable $exception) {
            throw new GatewayTransportException(
                message: $exception->getMessage(),
                outcome: $this->classifyTransportException($exception),
                previous: $exception,
            );
        }
    }

    private function classifyTransportException(Throwable $exception): GatewayOutcome
    {
        if ($exception instanceof ConnectionException) {
            if ($this->isTimeoutMessage($exception->getMessage())) {
                return GatewayOutcome::Timeout;
            }

            return GatewayOutcome::NetworkError;
        }

        if ($this->isTimeoutMessage($exception->getMessage())) {
            return GatewayOutcome::Timeout;
        }

        return GatewayOutcome::NetworkError;
    }

    private function isTimeoutMessage(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'timeout')
            || str_contains($normalized, 'timed out');
    }
}
