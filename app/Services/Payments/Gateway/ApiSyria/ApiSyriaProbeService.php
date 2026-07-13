<?php

namespace App\Services\Payments\Gateway\ApiSyria;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\DTOs\Payments\Gateway\GatewayPaymentAccount;
use App\Enums\Payments\PaymentWalletProvider;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;

/**
 * Phase 7.10 — read-only API Syria operations for live integration probing.
 */
final class ApiSyriaProbeService
{
    public function __construct(
        private ApiSyriaHttpClient $http,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->decodeSuccessfulBody(
            $this->http->get(
                config: GatewayProviderConfig::forProvider('apisyria'),
                query: ['resource' => 'status'],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function listAccounts(): array
    {
        return $this->decodeSuccessfulBody(
            $this->http->get(
                config: GatewayProviderConfig::forProvider('apisyria'),
                query: [
                    'resource' => 'accounts',
                    'action' => 'list',
                ],
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function findTransaction(string $transactionNumber, GatewayPaymentAccount $paymentAccount): array
    {
        $config = GatewayProviderConfig::forProvider('apisyria');

        $query = [
            'resource' => $paymentAccount->provider->value,
            'action' => 'find_tx',
            'tx' => $transactionNumber,
        ];

        if ($paymentAccount->provider === PaymentWalletProvider::Syriatel) {
            $query['gsm'] = $paymentAccount->accountIdentifier;
        } else {
            $query['account_address'] = $paymentAccount->accountIdentifier;
        }

        return $this->decodeSuccessfulBody(
            $this->http->get(config: $config, query: $query),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSuccessfulBody(GatewayHttpResponse $response): array
    {
        $body = $response->body;

        if ($body === null) {
            throw new \RuntimeException('API Syria returned a non-JSON response.');
        }

        if (! $response->successful()) {
            $message = is_string($body['error'] ?? null)
                ? $body['error']
                : 'API Syria request failed with HTTP '.$response->status;

            throw new \RuntimeException($message);
        }

        if (($body['success'] ?? true) === false) {
            $message = is_string($body['error'] ?? null)
                ? $body['error']
                : 'API Syria reported success=false';

            throw new \RuntimeException($message);
        }

        return $body;
    }
}
