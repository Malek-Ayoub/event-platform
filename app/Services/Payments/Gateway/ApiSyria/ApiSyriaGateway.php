<?php

namespace App\Services\Payments\Gateway\ApiSyria;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\Contracts\Payments\PaymentVerificationGateway;
use App\DTOs\Payments\Gateway\GatewayPaymentAccount;
use App\DTOs\Payments\Gateway\VerifyTransactionRequest;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Enums\Payments\PaymentWalletProvider;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use Throwable;

/**
 * Phase 7.10 — Manual Wallet Transfer via API Syria read API.
 *
 * Platform credentials come from config; merchant wallet context is supplied
 * per request via `GatewayPaymentAccount` on `VerifyTransactionRequest`.
 */
final class ApiSyriaGateway implements PaymentVerificationGateway
{
    public function __construct(
        private ApiSyriaHttpClient $http,
        private ApiSyriaResponseParser $parser,
        private GatewayResponseMapper $mapper,
    ) {}

    public function provider(): string
    {
        return 'apisyria';
    }

    public function verifyTransaction(VerifyTransactionRequest $request): VerifyTransactionResponse
    {
        $config = GatewayProviderConfig::forProvider($this->provider());
        $paymentAccount = $request->paymentAccount;

        $query = [
            'resource' => $paymentAccount->provider->value,
            'action' => 'find_tx',
            'tx' => $request->transactionNumber,
        ];

        if ($paymentAccount->provider === PaymentWalletProvider::Syriatel) {
            $query['gsm'] = $paymentAccount->accountIdentifier;
        } else {
            $query['account_address'] = $paymentAccount->accountIdentifier;
        }

        try {
            $response = $this->http->get($config, $query);
        } catch (Throwable $exception) {
            return $this->mapper->verifyTransactionTransportFailure(
                outcome: $this->mapper->classifyTransportException($exception),
                errorMessage: $exception->getMessage(),
            );
        }

        return $this->mapResponse(
            response: $response,
            paymentAccount: $paymentAccount,
        );
    }

    private function mapResponse(
        GatewayHttpResponse $response,
        GatewayPaymentAccount $paymentAccount,
    ): VerifyTransactionResponse {
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
                errorMessage: (string) ($bodyArray['error'] ?? $bodyArray['message'] ?? 'API Syria rejected the lookup request'),
                httpStatus: $response->status,
            );
        }

        if (($bodyArray['success'] ?? true) === false) {
            return $this->mapper->verifyTransactionTransportFailure(
                outcome: GatewayOutcome::Declined,
                errorMessage: (string) ($bodyArray['error'] ?? 'API Syria reported success=false'),
                httpStatus: $response->status,
            );
        }

        $parsed = $this->parser->parseFindTxResponse($bodyArray, $paymentAccount);

        return $this->mapper->verifyTransactionResult(
            found: $parsed['found'],
            amount: $parsed['amount'],
            currency: $parsed['currency'],
            receiverAccount: $parsed['receiverAccount'],
            providerTransactionId: $parsed['providerTransactionId'],
            rawStatus: $parsed['rawStatus'],
            raw: $parsed['raw'],
        );
    }
}
