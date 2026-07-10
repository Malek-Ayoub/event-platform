<?php

namespace App\Services\Payments;

use App\Domain\Correlation\Contracts\CorrelationContextInterface;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Exceptions\Payments\Gateway\GatewayOperationException;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Services\Payments\Data\GatewayRefundData;
use App\Services\Payments\Data\GatewayVerifyTransactionData;
use App\Services\Payments\Data\TransactionVerificationResult;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Support\GatewayProviderMetadata;
use App\Services\Payments\Mapping\RefundRequestMapper;
use App\Services\Payments\Mapping\VerifyTransactionRequestMapper;
use App\Services\Payments\Mapping\VerifyTransactionResponseMapper;
use App\Services\Refunds\Data\ProcessRefundData;
use App\Services\Refunds\Data\SubmitRefundData;
use App\Services\Refunds\RefundService;
use App\Support\Payments\PaymentCorrelation;

/**
 * Anti-Corruption Layer between external payment providers and domain services.
 */
final class PaymentGatewayService
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private CorrelationContextInterface $correlationContext,
        private RefundService $refundService,
        private RefundRequestMapper $refundRequestMapper,
        private VerifyTransactionRequestMapper $verifyTransactionRequestMapper,
        private VerifyTransactionResponseMapper $verifyTransactionResponseMapper,
    ) {}

    public function refund(GatewayRefundData $data): Refund
    {
        $refund = Refund::query()->whereKey($data->refundId)->firstOrFail();

        if ($refund->payment_transaction_id === null) {
            throw GatewayOperationException::forRefund(
                provider: 'unknown',
                outcome: GatewayOutcome::ProviderError,
                reason: "Refund {$refund->id} has no linked payment transaction",
            );
        }

        $payment = PaymentTransaction::query()
            ->whereKey($refund->payment_transaction_id)
            ->firstOrFail();

        $provider = strtolower(trim((string) $payment->provider));

        try {
            $gateway = $this->registry->refundGateway($provider);
        } catch (UnknownPaymentProviderException $exception) {
            throw GatewayOperationException::forRefund(
                provider: $provider,
                outcome: GatewayOutcome::ProviderError,
                reason: $exception->getMessage(),
            );
        }

        $gatewayRequest = $this->refundRequestMapper->toGatewayRequest(
            providerTransactionId: (string) $payment->provider_transaction_id,
            amount: number_format((float) $refund->amount, 2, '.', ''),
            currency: strtoupper((string) ($payment->currency ?: 'USD')),
            reason: $refund->reason,
            providerRefundId: $refund->provider_refund_id,
            metadata: [
                'refund_id' => $refund->id,
                'order_id' => $refund->order_id,
            ],
        );
        $gatewayResponse = $gateway->refund($gatewayRequest);

        $this->assertSuccessfulRefund($provider, $gatewayResponse);

        $this->correlationContext->bind(
            PaymentCorrelation::forProviderTransaction($provider, $gatewayResponse->providerRefundId),
        );

        try {
            if ($this->isImmediateRefundCompletion($gatewayResponse->status)) {
                return $this->refundService->processRefund(new ProcessRefundData(
                    refundId: (int) $refund->id,
                    providerRefundId: $gatewayResponse->providerRefundId,
                    actor: $data->actor,
                    ipAddress: $data->ipAddress,
                ));
            }

            return $this->refundService->submitRefund(new SubmitRefundData(
                refundId: (int) $refund->id,
                providerRefundId: $gatewayResponse->providerRefundId,
                actor: $data->actor,
                ipAddress: $data->ipAddress,
            ));
        } finally {
            $this->correlationContext->clear();
        }
    }

    public function verifyTransaction(GatewayVerifyTransactionData $data): TransactionVerificationResult
    {
        $provider = strtolower(trim($data->provider));

        try {
            $gateway = $this->registry->verificationGateway($provider);
        } catch (UnknownPaymentProviderException $exception) {
            throw GatewayOperationException::forVerify(
                provider: $provider,
                outcome: GatewayOutcome::ProviderError,
                reason: $exception->getMessage(),
            );
        }

        $gatewayRequest = $this->verifyTransactionRequestMapper->toGatewayRequest(
            transactionNumber: $data->transactionNumber,
            expectedAmount: $data->expectedAmount,
            expectedCurrency: $data->expectedCurrency,
            paymentAccount: $data->paymentAccount,
        );

        $gatewayResponse = $gateway->verifyTransaction($gatewayRequest);

        $this->assertSuccessfulVerifyLookup($provider, $gatewayResponse);

        return $this->verifyTransactionResponseMapper->toDomainResult(
            response: $gatewayResponse,
            expectedAmount: $data->expectedAmount,
            expectedCurrency: $data->expectedCurrency,
            expectedReceiverAccount: $data->paymentAccount->receiverAccount(),
        );
    }

    private function assertSuccessfulRefund(string $provider, RefundResponse $response): void
    {
        if ($response->outcome === GatewayOutcome::Success && $response->providerRefundId !== '') {
            return;
        }

        throw GatewayOperationException::forRefund(
            provider: $provider,
            outcome: $response->outcome,
            reason: $this->gatewayFailureReason($response->providerMetadata, 'Provider rejected refund request'),
        );
    }

    private function assertSuccessfulVerifyLookup(string $provider, VerifyTransactionResponse $response): void
    {
        if ($response->outcome === GatewayOutcome::Success) {
            return;
        }

        throw GatewayOperationException::forVerify(
            provider: $provider,
            outcome: $response->outcome,
            reason: $this->gatewayFailureReason($response->providerMetadata, 'Provider rejected transaction lookup'),
        );
    }

    private function isImmediateRefundCompletion(string $status): bool
    {
        return in_array(strtolower($status), ['completed', 'processed', 'success'], true);
    }

    /**
     * @param  array<string, mixed>  $providerMetadata
     */
    private function gatewayFailureReason(array $providerMetadata, string $fallback): string
    {
        $raw = $providerMetadata[GatewayProviderMetadata::RAW] ?? [];

        if (! is_array($raw)) {
            return $fallback;
        }

        foreach (['error', 'message'] as $key) {
            if (isset($raw[$key]) && (string) $raw[$key] !== '') {
                return (string) $raw[$key];
            }
        }

        return $fallback;
    }
}
