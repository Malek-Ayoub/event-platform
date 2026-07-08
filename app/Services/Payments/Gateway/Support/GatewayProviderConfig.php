<?php

namespace App\Services\Payments\Gateway\Support;

readonly class GatewayProviderConfig
{
    public function __construct(
        public string $provider,
        public string $baseUrl,
        public string $apiKey,
        public string $webhookSecret,
        public string $signatureHeader,
        public string $initiatePath,
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

        return new self(
            provider: $provider,
            baseUrl: (string) ($config['base_url'] ?? ''),
            apiKey: (string) ($config['api_key'] ?? ''),
            webhookSecret: (string) ($config['webhook_secret'] ?? ''),
            signatureHeader: (string) ($config['signature_header'] ?? 'X-Signature'),
            initiatePath: (string) ($config['initiate_path'] ?? '/payments'),
            refundPath: (string) ($config['refund_path'] ?? '/refunds'),
            connectTimeout: (int) ($http['connect_timeout'] ?? 5),
            requestTimeout: (int) ($http['request_timeout'] ?? 30),
            retryAttempts: (int) ($http['retry_attempts'] ?? 2),
            retryDelayMs: (int) ($http['retry_delay_ms'] ?? 250),
        );
    }
}
