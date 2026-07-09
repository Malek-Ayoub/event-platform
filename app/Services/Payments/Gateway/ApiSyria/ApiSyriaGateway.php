<?php

namespace App\Services\Payments\Gateway\ApiSyria;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\Contracts\Payments\PaymentVerificationGateway;
use App\DTOs\Payments\Gateway\VerifyTransactionRequest;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use Throwable;

/**
 * Batch 7.6 — Manual Wallet Transfer (IMPLEMENTATION_ROADMAP.md §7.9.4).
 *
 * API Syria exposes only a transaction-lookup endpoint (`find_tx`) — no
 * hosted checkout, no webhooks. This gateway performs the lookup only; all
 * business validation (amount/currency/receiver matching) happens in the ACL
 * mapper (`VerifyTransactionResponseMapper`), not here.
 */
final class ApiSyriaGateway implements PaymentVerificationGateway
{
    public function __construct(
        private PaymentGatewayHttpClient $http,
        private GatewayResponseMapper $mapper,
    ) {}

    public function provider(): string
    {
        return 'apisyria';
    }

    public function verifyTransaction(VerifyTransactionRequest $request): VerifyTransactionResponse
    {
        $config = GatewayProviderConfig::forProvider($this->provider());

        try {
            $response = $this->http->post(
                config: $config,
                path: $config->verifyTransactionPath,
                payload: [
                    'transaction_number' => $request->transactionNumber,
                ],
            );
        } catch (Throwable $exception) {
            return $this->mapper->verifyTransactionTransportFailure(
                outcome: $this->mapper->classifyTransportException($exception),
                errorMessage: $exception->getMessage(),
            );
        }

        return $this->mapResponse($response);
    }

    private function mapResponse(GatewayHttpResponse $response): VerifyTransactionResponse
    {
        $bodyArray = $response->body;

        if ($bodyArray === null) {
            return $this->mapper->verifyTransactionTransportFailure(
                outcome: GatewayOutcome::Unknown,
                errorMessage: 'Non-JSON response from API Syria',
                httpStatus: $response->status,
            );
        }

        if (! $response->successful()) {
            return $this->mapper->verifyTransactionTransportFailure(
                outcome: $this->mapper->classifyHttpResponse($response, $bodyArray),
                errorMessage: (string) ($bodyArray['message'] ?? $bodyArray['error'] ?? 'API Syria rejected the lookup request'),
                httpStatus: $response->status,
            );
        }

        $found = (bool) ($bodyArray['found'] ?? ($bodyArray['transaction_id'] ?? $bodyArray['id'] ?? null) !== null);

        return $this->mapper->verifyTransactionResult(
            found: $found,
            amount: isset($bodyArray['amount']) ? (string) $bodyArray['amount'] : null,
            currency: isset($bodyArray['currency']) ? (string) $bodyArray['currency'] : null,
            receiverAccount: isset($bodyArray['receiver_account']) ? (string) $bodyArray['receiver_account'] : null,
            providerTransactionId: isset($bodyArray['transaction_id'])
                ? (string) $bodyArray['transaction_id']
                : (isset($bodyArray['id']) ? (string) $bodyArray['id'] : null),
            rawStatus: isset($bodyArray['status']) ? (string) $bodyArray['status'] : null,
            raw: $bodyArray,
        );
    }
}
