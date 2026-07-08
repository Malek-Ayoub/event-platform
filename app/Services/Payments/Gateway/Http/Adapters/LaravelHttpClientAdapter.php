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
        try {
            $pending = $this->http
                ->acceptJson()
                ->asJson()
                ->withHeaders($headers)
                ->connectTimeout($connectTimeout)
                ->timeout($requestTimeout);

            if ($retryAttempts > 0) {
                $pending = $pending->retry(
                    times: $retryAttempts,
                    sleepMilliseconds: $retryDelayMs,
                    when: static fn ($exception): bool => $exception instanceof ConnectionException,
                    throw: false,
                );
            }

            $response = $pending->post($url, $payload);
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
