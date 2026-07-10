<?php

namespace App\Services\Payments\Gateway\Support;

readonly class GatewayProviderConfig
{
    public function __construct(
        public string $provider,
        public string $baseUrl,
        public string $apiKey,
        public string $refundPath,
        public int $connectTimeout,
        public int $requestTimeout,
        public int $retryAttempts,
        public int $retryDelayMs,
    ) {}

    public static function forProvider(string $provider): self
    {
        /** @var array<string, mixed>|null $config */
        $config = config("payment_gateways.providers.{$provider}");

        if (! is_array($config)) {
            throw new \InvalidArgumentException("Payment gateway provider [{$provider}] is not configured.");
        }

        /** @var array<string, mixed> $http */
        $http = config('payment_gateways.http', []);

        /** @var array<string, mixed> $timeouts */
        $timeouts = is_array($config['timeouts'] ?? null) ? $config['timeouts'] : [];

        /** @var array<string, mixed> $retry */
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];

        return new self(
            provider: $provider,
            baseUrl: (string) ($config['base_url'] ?? ''),
            apiKey: (string) ($config['api_key'] ?? ''),
            refundPath: (string) ($config['refund_path'] ?? '/refunds'),
            connectTimeout: (int) ($timeouts['connect'] ?? $http['connect_timeout'] ?? 5),
            requestTimeout: (int) ($timeouts['request'] ?? $http['request_timeout'] ?? 30),
            retryAttempts: (int) ($retry['attempts'] ?? $http['retry_attempts'] ?? 2),
            retryDelayMs: (int) ($retry['delay_ms'] ?? $http['retry_delay_ms'] ?? 250),
        );
    }
}
